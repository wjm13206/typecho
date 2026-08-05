<?php

namespace Widget;

use Typecho\Common;
use Exception;
use Typecho\Widget\Exception;
use Widget\Base\Options as BaseOptions;
use Utils\Upgrade as UpgradeAction;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 升级组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Upgrade extends BaseOptions implements ActionInterface
{
    /**
     * minimum supported version
     */
    public const MIN_VERSION = '1.1.0';

    /**
     * 执行升级程序
     *
     * @throws \Typecho\Db\Exception
     */
    public function upgrade()
    {
        $currentVersion = $this->options->version;

        if (version_compare($currentVersion, self::MIN_VERSION, '<')) {
            Notice::alloc()->set(
                _t('请先升级至版本 %s', self::MIN_VERSION),
                'error'
            );

            $this->response->goBack();
        }

        $ref = new \ReflectionClass(UpgradeAction::class);
        $message = [];

        foreach ($ref->getMethods() as $method) {
            preg_match("/^v([_0-9]+)$/", $method->getName(), $matches);
            $version = str_replace('_', '.', $matches[1]);

            if (version_compare($currentVersion, $version, '>=')) {
                continue;
            }

            $options = Options::allocWithAlias($version);

            /** 执行升级脚本 */
            try {
                $result = $method->invoke(null, $this->db, $options);
                if (!empty($result)) {
                    $message[] = $result;
                }
            } catch (Exception $e) {
                Notice::alloc()->set($e->getMessage(), 'error');
                $this->response->goBack();
            }

            /** 更新版本号 */
            $this->update(
                ['value' => 'Typecho ' . $version],
                $this->db->sql()->where('name = ?', 'generator')
            );

            Options::destroy($version);
        }

        /** 更新版本号 */
        $this->update(
            ['value' => 'Typecho ' . Common::VERSION],
            $this->db->sql()->where('name = ?', 'generator')
        );

        Notice::alloc()->set(
            empty($message) ? _t("升级已经完成") : $message,
            empty($message) ? 'success' : 'notice'
        );
    }

    /**
     * 在线升级
     *
     * 上传 Typecho 源码压缩包并就地覆盖核心文件, 完成后跳转到升级程序执行数据库升级.
     *
     * @throws \Typecho\Db\Exception
     */
    public function upgradeOnline()
    {
        $tmpDir = null;
        $backupDir = null;

        try {
            if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                throw new Exception(_t('请先选择要上传的升级包文件'));
            }

            $file = $_FILES['file'];
            $fileName = basename($file['name']);
            $lowerName = strtolower($fileName);

            $isZip = substr($lowerName, -4) == '.zip';
            $isTar = substr($lowerName, -7) == '.tar.gz'
                || substr($lowerName, -4) == '.tgz'
                || substr($lowerName, -4) == '.tar';

            if (!$isZip && !$isTar) {
                throw new Exception(_t('升级包格式不正确, 仅支持 .zip 或 .tar.gz 格式'));
            }

            // 临时工作目录
            $tmpDir = __TYPECHO_ROOT_DIR__ . '/usr/upgrade/tmp-' . uniqid();
            $pkgDir = $tmpDir . '/pkg';
            if (!@mkdir($tmpDir, 0755, true) || !@mkdir($pkgDir, 0755, true)) {
                throw new Exception(_t('无法创建临时目录, 请检查 %s 目录的写入权限', '/usr/upgrade'));
            }

            $archivePath = $tmpDir . '/' . $fileName;
            if (substr($lowerName, -4) == '.tgz') {
                $archivePath = $tmpDir . '/package.tar.gz';
            }
            if (!@move_uploaded_file($file['tmp_name'], $archivePath)) {
                throw new Exception(_t('无法保存上传的升级包, 请检查服务器权限'));
            }

            // 解压
            if ($isZip) {
                $this->extractZip($archivePath, $pkgDir);
            } else {
                $this->extractTar($archivePath, $pkgDir);
            }

            // 定位包根目录
            $root = $this->findPackageRoot($pkgDir);
            if (empty($root) || !$this->isValidPackage($root)) {
                throw new Exception(_t('上传的文件不是有效的 Typecho 升级包'));
            }

            // 读取新版本号
            $newVersion = $this->detectVersion($root);
            $currentVersion = $this->options->version;

            if (!empty($newVersion) && version_compare($newVersion, $currentVersion, '<')) {
                throw new Exception(_t('您上传的版本(%s)低于当前版本(%s), 无需升级', $newVersion, $currentVersion));
            }

            // 检查目录可写
            $check = ['', 'admin', 'var', 'install', 'index.php', 'install.php'];
            foreach ($check as $item) {
                $path = '' == $item ? __TYPECHO_ROOT_DIR__ : __TYPECHO_ROOT_DIR__ . '/' . $item;
                $path = file_exists($path) ? $path : dirname($path);
                if (!is_writable($path)) {
                    throw new Exception(_t('目录 %s 不可写, 无法完成升级', '/' . $item));
                }
            }

            // 备份将被覆盖的核心文件
            $backupDir = __TYPECHO_ROOT_DIR__ . '/usr/upgrade/backup-' . date('YmdHis');
            if (!@mkdir($backupDir, 0755, true)) {
                throw new Exception(_t('无法创建备份目录, 请检查 %s 目录的权限', '/usr/upgrade'));
            }

            $managed = ['index.php', 'install.php', 'admin', 'var', 'install', 'LICENSE.txt'];
            foreach ($managed as $item) {
                if (file_exists(__TYPECHO_ROOT_DIR__ . '/' . $item)) {
                    $this->copyItem(__TYPECHO_ROOT_DIR__ . '/' . $item, $backupDir . '/' . $item);
                }
            }

            // 覆盖安装
            $this->copyPackage($root, __TYPECHO_ROOT_DIR__);

            // 清理临时文件
            $this->removeDir($tmpDir);

            $notice = empty($newVersion)
                ? _t('文件已更新完成')
                : (_t('文件已更新至 %s 版本', $newVersion)
                    . (version_compare($newVersion, $currentVersion, '>')
                        ? ', ' . _t('正在执行数据库升级') : ''));

            Notice::alloc()->set($notice, 'success');

            $auto = !empty($newVersion) && version_compare($newVersion, $currentVersion, '>');
            $target = Common::url('upgrade.php', $this->options->adminUrl) . ($auto ? '?auto=1' : '');
            $this->response->redirect($target);
            return;
        } catch (Exception $e) {
            if (!empty($tmpDir)) {
                $this->removeDir($tmpDir);
            }

            Notice::alloc()->set($e->getMessage(), 'error');
            $this->response->goBack();
        }
    }

    /**
     * 解压 zip 压缩包
     *
     * @param string $archive 压缩包路径
     * @param string $dest 解压目录
     * @throws Exception
     */
    private function extractZip(string $archive, string $dest)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception(_t('服务器未启用 ZipArchive 扩展, 无法解压 zip 升级包'));
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($archive)) {
            throw new Exception(_t('无法打开升级包, 文件可能已损坏'));
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (!$this->isSafePath($entry)) {
                $zip->close();
                throw new Exception(_t('升级包中包含非法路径, 已中止升级'));
            }
            $entries[] = $entry;
        }

        $zip->extractTo($dest, $entries);
        $zip->close();
    }

    /**
     * 解压 tar / tar.gz 压缩包
     *
     * @param string $archive 压缩包路径
     * @param string $dest 解压目录
     * @throws Exception
     */
    private function extractTar(string $archive, string $dest)
    {
        if (!class_exists('PharData')) {
            throw new Exception(_t('服务器未启用 PharData 扩展, 无法解压 tar.gz 升级包'));
        }

        $phar = new \PharData($archive);
        $base = 'phar://' . str_replace('\\', '/', $phar->getPathname());
        $entries = [];

        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (0 === strpos($path, $base)) {
                $rel = rawurldecode(substr($path, strlen($base) + 1));
                if (!empty($rel) && !$this->isSafePath($rel)) {
                    throw new Exception(_t('升级包中包含非法路径, 已中止升级'));
                }
                $entries[] = $rel;
            }
        }

        $phar->extractTo($dest, $entries);
    }

    /**
     * 判断路径是否安全
     *
     * @param string $path
     * @return bool
     */
    private function isSafePath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        if ('' === $path) {
            return false;
        }

        if ('/' === $path[0] || preg_match('/^[a-zA-Z]:/', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $part) {
            if ('..' == $part) {
                return false;
            }
        }

        return true;
    }

    /**
     * 定位压缩包根目录
     *
     * @param string $dir
     * @return string
     */
    private function findPackageRoot(string $dir): string
    {
        if ($this->isValidPackage($dir)) {
            return $dir;
        }

        $items = array_values(array_filter(scandir($dir), function ($item) {
            return '.' != $item && '..' != $item;
        }));

        if (1 == count($items) && is_dir($dir . '/' . $items[0])) {
            $root = $dir . '/' . $items[0];
            if ($this->isValidPackage($root)) {
                return $root;
            }
        }

        return '';
    }

    /**
     * 判断是否为有效的 Typecho 包
     *
     * @param string $dir
     * @return bool
     */
    private function isValidPackage(string $dir): bool
    {
        return is_file($dir . '/index.php')
            && is_dir($dir . '/var')
            && is_file($dir . '/var/Typecho/Common.php')
            && is_dir($dir . '/admin')
            && is_dir($dir . '/install');
    }

    /**
     * 从包内读取 Typecho 版本号
     *
     * @param string $dir
     * @return string
     */
    private function detectVersion(string $dir): string
    {
        $content = @file_get_contents($dir . '/var/Typecho/Common.php');
        if (false === $content) {
            return '';
        }

        return preg_match("/const VERSION\s*=\s*'([0-9.]+)'/", $content, $matches)
            ? $matches[1]
            : '';
    }

    /**
     * 拷贝单个文件或目录
     *
     * @param string $src
     * @param string $dest
     */
    private function copyItem(string $src, string $dest)
    {
        if (is_dir($src)) {
            if (!is_dir($dest)) {
                @mkdir($dest, 0755, true);
            }

            foreach (scandir($src) as $item) {
                if ('.' != $item && '..' != $item) {
                    $this->copyItem($src . '/' . $item, $dest . '/' . $item);
                }
            }
        } else {
            @copy($src, $dest);
            @chmod($dest, 0644);
        }
    }

    /**
     * 拷贝升级包内容到安装目录, 排除用户数据
     *
     * @param string $src
     * @param string $dest
     */
    private function copyPackage(string $src, string $dest)
    {
        foreach (scandir($src) as $item) {
            if ('.' == $item || '..' == $item) {
                continue;
            }

            if (in_array($item, ['usr', '.git', 'config.inc.php'])) {
                continue;
            }

            $this->copyItem($src . '/' . $item, $dest . '/' . $item);
        }
    }

    /**
     * 递归删除目录
     *
     * @param string $dir
     */
    private function removeDir(string $dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ('.' != $item && '..' != $item) {
                $path = $dir . '/' . $item;
                is_dir($path) ? $this->removeDir($path) : @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * 初始化函数
     *
     * @throws \Typecho\Db\Exception
     * @throws \Typecho\Widget\Exception
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost() && $this->request->is('do=online'))->upgradeOnline();
        $this->on($this->request->isPost())->upgrade();
        $this->response->redirect($this->options->adminUrl);
    }
}
