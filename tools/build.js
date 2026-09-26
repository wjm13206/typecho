const sass = require('sass'),
    fs = require('fs'),
    Terser = require("terser"),
    srcDir = __dirname + '/../admin/src',
    distDir = __dirname + '/../admin',
    themeDir = __dirname + '/../usr/themes/classic-22',
    action = process.argv.pop();

const logger = {
    warn: function (message, options) {
        if (options.deprecation) {
            return;
        }

        console.warn(message);
    },
    debug: function () {}
};

function buildSass(file, dist, sassDir)
{
    let outFile = dist + '/' + file.split('.')[0] + '.css';
    console.log('processing ', file);

    try {
        let result = sass.compile(sassDir + '/' + file, {
            style: 'compressed',
            loadPaths: [sassDir],
            logger: logger
        });

        fs.writeFileSync(outFile, result.css.toString() + '\n');
    } catch (error) {
        console.error('Error: ' + error.message);
    }
}

async function minifyJs(file, dist)
{
    console.log('minify ', file);
    const code = fs.readFileSync(srcDir + '/js/' + file).toString('utf8');

    // terser 是异步的，且能正确处理 ES6+（新版 DOMPurify 为 ES6 语法，
    // 旧 uglify-js 会压坏或报错）。失败时保留旧产物并报错退出，不再写入 undefined。
    const result = await Terser.minify(code);

    if (result.error) {
        console.error('Error: ' + result.error.message);
        process.exitCode = 1;
        return;
    }

    fs.writeFileSync(dist + '/' + file, result.code);
}

function listFiles(dir, regExp)
{
    let files = fs.readdirSync(dir), result = [];

    files.map(function (file) {
        if (file.match(regExp)) {
            result.push(file);
        }
    });

    return result;
}

if (action === 'css') {
    console.log('build css');

    listFiles(srcDir + '/scss', /^[a-z0-9-]+\.scss$/).forEach(function (file) {
        buildSass(file, distDir + '/css', srcDir + '/scss');
    });
} else if (action === 'js') {
    console.log('build js');

    (async function () {
        for (const file of listFiles(srcDir + '/js', /^[-\w]+\.js$/)) {
            await minifyJs(file, distDir + '/js');
        }
    })();
} else if (action === 'theme_css') {
    console.log('build theme css');

    listFiles(themeDir + '/static/scss', /^[a-z0-9-]+\.scss$/).forEach(function (file) {
        buildSass(file, themeDir + '/static/css', themeDir + '/static/scss');
    });
} else {
    console.log('Please choose correct action.');
}