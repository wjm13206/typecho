const sass = require('sass'),
    fs = require('fs'),
    UglifyJS = require("uglify-js"),
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

function minifyJs(file, dist)
{
    console.log('minify ', file);
    let code = {};
    code[file] = fs.readFileSync(srcDir + '/js/' + file).toString('utf8');

    fs.writeFileSync(
        dist + '/' + file,
        UglifyJS.minify(code).code
    );
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

    listFiles(srcDir + '/js', /^[-\w]+\.js$/).forEach(function (file) {
        minifyJs(file, distDir + '/js');
    });
} else if (action === 'theme_css') {
    console.log('build theme css');

    listFiles(themeDir + '/static/scss', /^[a-z0-9-]+\.scss$/).forEach(function (file) {
        buildSass(file, themeDir + '/static/css', themeDir + '/static/scss');
    });
} else {
    console.log('Please choose correct action.');
}