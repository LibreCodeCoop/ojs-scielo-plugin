# Import SciELO XML

Import SciELO XML into OJS.

## How to usage

### Install

Clone and run install:

```
git clone --progress -b "${OJS_VERSION}" --single-branch --depth 1 --recurse-submodules -j 4 https://github.com/librecodecoop/ojs-scielo-plugin plugins/importexport/scielo
php lib/pkp/tools/installPluginVersion.php plugins/importexport/scielo/version.xml
```

### Import

Run this command line to see the usage instructions:

```bash
php tools/importExport.php ScieloPlugin
```

## Run unit tests

```bash
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit \
    --configuration plugins/importexport/scielo/phpunit.xml.dist \
    plugins/importexport/scielo/tests/
```
