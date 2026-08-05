# Typecho build tools
DIR=../

# Lint all PHP files (equivalent to the CI syntax check)
test:
	for i in `find ../var/ -name '*.php'`; do php -l $$i || exit 1; done;
	for i in `find ../usr/ -name '*.php'`; do php -l $$i || exit 1; done;
	for i in `find ../admin/ -name '*.php'`; do php -l $$i || exit 1; done;

# Compile SCSS / minify JS
css:
	npm run build_css

css-theme:
	npm run build_css:theme

js:
	npm run build_js

# Regenerate the messages.pot template
pot:
	php build_pot.php > messages.pot