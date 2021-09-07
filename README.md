# Rozier backend theme

## Migration to ES6 syntax and Webpack
### Things to tests

* Map markers (and images)
* History js (back and forwards not working)

## Contribute

To enhance Rozier backend theme you must install Grunt and Bower:

```shell
cd src
yarn install
# Launch Grunt to generate prod files
yarn build
# Or… launch watch grunt when you’re
# working on LESS and JS files.
yarn dev
```

Then you will be able to switch theme to development mode
in `RozierApp.php`:

```php
$this->assignation['head']['backDevMode'] = true;
```

This will make Rozier theme to load each Roadiz JS file and Bower
components separately.

**Do not forget to set `$this->assignation['head']['backDevMode']` to `false` and to run
`grunt` before pushing your code!**
