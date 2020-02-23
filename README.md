# Import SciELO XML

Import SciELO XML into OJS.

## How to usage

Install the plugin and run this command line to see the usage instructions:

```bash
php tools/importExport.php ScieloPlugin
```

## Run unit tests

```bash
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit \
    --configuration plugins/importexport/scielo/phpunit.xml.dist \
    plugins/importexport/scielo/tests/
```