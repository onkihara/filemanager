const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

// files - resources
mix.js('resources/js/app.js', 'public/js')
    .extract(['vue', 'axios', 'lodash']).vue();

mix.sass('resources/sass/app.scss', 'public/css/bootstrap.css');
mix.sass('resources/sass/files.scss', 'public/css/files.css');


