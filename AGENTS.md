# AGENTS.md

Typecho 博客平台。纯 PHP，无 Composer 依赖、无单元测试框架。前端控制器 `index.php`（加载 `config.inc.php` → `Widget\Init::alloc()` → `Typecho\Router::dispatch()`）。

## 运行前提
- 仓库不含 `config.inc.php`（被 .gitignore 排除）。本地运行前先访问 `install.php` 安装生成，或手写一份；缺文件时访问会跳转到安装页。
- 支持 MySQL/MariaDB、SQLite、PostgreSQL，各库表单在 `install/*.sql`。

## 目录与架构
- `var/` 核心库。`var/Typecho/` 为框架（Router、Db、Plugin、Config…），`var/Widget/` 为"MVC 的 VC"（逻辑+模板输出），按功能分 `Contents/Comments/Users/Options/Themes` 等子目录。
- Widget 通过 `Widget::widget()` / `Widget::alloc()` 创建，是插件、主题、请求分发的核心机制。改业务逻辑前先看懂 `var/Typecho/Widget.php` 的堆栈/`____` 魔术方法。
- `admin/` 后台（PHP 页面 + `src/` 为 SCSS/JS 源码）。`usr/` 运行时目录（主题、插件、上传，多数被 gitignore）。
- 类名兼容旧式下划线：自动加载器 `var/Typecho/Common.php:79` 会把 `Typecho\Foo` 与 `Typecho_Foo` 互相映射。插件用 `_` 命名或 `TypechoPlugin\` 命名空间均可。

## 命令
- 语法检查/CI 测试：CI（`.github/workflows/Typecho-dev-Ci.yml`）跨 PHP 7.4–8.2 对全部 `*.php` 跑 `php -l -n`。本地等价：`make test`（见 `tools/Makefile`）。改 PHP 后务必自查 `php -l`。
- 改 SCSS/JS 后需重新构建并**提交产物**（CI 构建包排除 `src`）：在 `tools/` 下 `npm install`，然后 `npm run build_js` / `npm run build_css` / `npm run build_css:theme`（`tools/build.js`，node-sass + uglify）。生成物落在 `admin/css`、`admin/js`、主题 `static/css`。

## 约定
- 指码风格（`.editorconfig`）：PHP 用 4 空格缩进、文件末尾空行；`.php` 强制换行。YAML/SCSS 用 2 空格。
- 提交消息用英文祈使句，符合仓库风格（见 `git log`）。
- `usr/themes/` 默认内置 `default` 与 `classic-22`，其余主题/插件/用户内容不入库。