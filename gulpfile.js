/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * 2020-05-24 TC moOde 6.6.0
 */


/**
 * 
 * Description: gulp buildscript for frontend 
 * 
 * Features:
 * - provide automatic bundling of the javascripts files.
 * - uses a cache for speed
 * - provide sourcemaps for browser side debugging
 * - patches files to use the bundles
 * - easy way to deploy
 * - webserver for frontend development (backup running on moode instance, webserver on desktop computer, uses proxy) 
 * 
 * Usage:
 * 
 * 
 * Install:
 * - install npm (apt-get npm, mac and windows download and running installers)
 * - from the moode git repro directory run npm ci (installs the required npm modules)
 * - call a build target
 * - check if your environment is ok by running gulp -v .
 *   - if says 'command not found', run node_modules/.bin/gulp instead
 * - now you can run commands like:
 * 
 * Main build targets:
 *  gulp clean [--all]          - Empties the build/build and build/distr directory, with the --all also the cache is flushed.
 * 
 *  gulp build [--force]        - Build frontend from {app.src} and put output in {app.dest}
 *  gulp watch [--build]   - Start web server with as root {app.src} with proxy to moode, used for local web development. 
 *             [--force]                 When --build is given, also perform a build and use {app.dest} as web root
 *  gulp deploy [--test]        - Deploys everything needed (inc php etc) {app.deploy}.
 *              [--force]                  With the option --test deploy to build/dist (app.dist).
 *                                When used to real don't forget to sudo first
 * 
 *  Default most task only update/use files that are changed (= is newer).  With force the files are also updated.
 * 
 * Sub build targets (are automaticly called by the main build targets on need):
 *  gulp cache                  - fill cache with files (basedon changed and index.html)
 *  gulp bundle                 - (re)bundle the files
 *  gulp listplugins            - list available gulp plugins
 * 
 * Generated Directory tree:
 * 
 * moode
 *  |- build               - directory used by the build scripts for temporary files, no need to checkin. maybe cache checkin for speed.
 *     |- cache            - temporary cache with all individual minified js/css files
 *          |- css         - stylesheets
 *          |- javascript  - javascript
 *          |- maps        - matching sourcemaps
 *     |- build            - all build bundles, including enough files to run again a local js development server
 *     |- distr            - result of a test deploy
 * 
 * 
 * Inspiration taken from:
 * - https://css-tricks.com/gulp-for-beginners/
 * - https://nystudio107.com/blog/a-gulp-workflow-for-frontend-development-automation
 * 
 */

// package vars
const pkg  = require("./package.json");

// gulp
const gulp = require("gulp");
var autoprefixer = require('autoprefixer');
var cssnano = require('cssnano');
const path = require('path');

// load all plugins in 'devDependencies' into the variable $
const $ = require('gulp-load-plugins')({
    pattern: ['*'],
    scope: ['devDependencies']
});

// Error checking; produce an error rather than crashing.
var onError = function(err) {
    console.log(err.toString());
    this.emit('end');
};

// banner used for generated js and css files
const banner = [
    "/**",
    " * <%= pkg.description %> (C) <%= pkg.copyright %> <%= pkg.author %>",
    " * <%= pkg.homepage %>",       
    " *",  
    " * This Program is free software; you can redistribute it and/or modify",
    " * it under the terms of the GNU General Public License as published by",
    " * the Free Software Foundation; either version 3, or (at your option)",
    " * any later version.",
    " *",
    " * This Program is distributed in the hope that it will be useful,",
    " * but WITHOUT ANY WARRANTY; without even the implied warranty of",
    " * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the",
    " * GNU General Public License for more details.",
    " *",
    " * You should have received a copy of the GNU General Public License",
    " * along with this program.  If not, see <http://www.gnu.org/licenses/>.",
    " *",
    " * @version        <%= pkg.version %>",
    " * @build          " + $.moment().format("llll") + " ET",
//    " * @release        " + $.gitRevSync.long() + " [" + $.gitRevSync.branch() + "]",
    " *",
    " */",
    ""
].join("\n");

// banner used for generated html files
// const banner_html = [    
//     "<!--",
//     "/**",
//     " * <%= pkg.description %> (C) <%= pkg.copyright %> <%= pkg.author %>",
//     " * <%= pkg.homepage %>",   
//     " *",  
//     " * @project        <%= pkg.name %>",
//     " * @version        <%= pkg.version %>",
//     " * @author         <%= pkg.author %>",
//     " * @build          " + $.moment().format("llll") + " ET",
// //    " * @release        " + $.gitRevSync.long() + " [" + $.gitRevSync.branch() + "]",
//     " * @copyright      Copyright (c) <%= pkg.copyright %>",
//     " *",
//     " */",
//     "-->",
//     ""
// ].join("\n");

// shorthand date stamp
const CURRENT_DATE= $.moment().format("YYYY-MM-DD")

// used to automatic replace certain patterns in files
const REPLACEMENT_PATTERNS= [
    {
      match: '__CURRENTDATE__',
      replacement: CURRENT_DATE
    },
    {
      match: '__VERSION__',
      replacement: pkg.version
    },
    {
      match: '__COPYRIGHT__',
      replacement: pkg.copyright
    }            
  ];

// configure the gulp mode options
const mode = $.mode( {  modes: ["build", "development", "test", "all", "force"], 
                        default: "development",
                        verbose: false});

const DEPLOY_LOCATION = mode.test() ? pkg.app.dist: pkg.app.deploy;

/***************************************************************************
 * GULP TASKS START HERE
 ***************************************************************************/

 // only used to check the name of the gulp plugins
gulp.task('listplugins', function(done) {
  console.log('List of available plugins:');
  console.log($);
  done();
});

gulp.task('sass', function(done) {
    // while isn't installed don't use it
    // install gulp-sass to enable support
    if($.sass) {
        return gulp.src('app/scss/**/*.scss') // Gets all files ending with .scss in app/scss
            .pipe($.sass())
            .pipe(gulp.dest('app/css'))
            .on('end', done);
    } else {
        done();
    }
});

// start an embedded webserver for local development, each call to backend requires  proxy entry to handle it
gulp.task('browserSync', function(done) {
    $.browserSync.init({
        host: '0.0.0.0',
        server: {
            baseDir: mode.build() ? pkg.app.dest: pkg.app.src,
        },
        middleware: [ 
            $.httpProxyMiddleware.createProxyMiddleware('/imagesw/thmcache',  { target: pkg.server.proxy, changeOrigin: true }),
            $.httpProxyMiddleware.createProxyMiddleware('/imagesw/radio-logos',  { target: pkg.server.proxy, changeOrigin: true }),
            $.httpProxyMiddleware.createProxyMiddleware('/command/',  { target: pkg.server.proxy , changeOrigin: true}),
            $.httpProxyMiddleware.createProxyMiddleware('/*.php',  { target: pkg.server.proxy, changeOrigin: true }),
            $.httpProxyMiddleware.createProxyMiddleware('/*.php/*',  { target: pkg.server.proxy, changeOrigin: true })
                    ]
    });
    done();
});

gulp.task('browserReload', function(done) {
     $.browserSync.reload();
     done();
});

function fileNameToMin(relpath) {
    var dirname = path.dirname(relpath),
        ext =path.extname(relpath),
        basename =path.basename(relpath, ext);

    if(ext === '.js' ||ext === '.css') {
        ext = '.min'+ ext ;
    }

    var newrelpath= path.join(dirname, basename + ext);
    return newrelpath;
}

gulp.task('cache', function(done){
    var plugins=[
        autoprefixer({cascade: false}),
        cssnano()
    ];
    return gulp.src(pkg.app.src+'/index.html')
        .pipe($.replace(/.*BUNDLE_TAG/g, "")) // remove comment blocks to in clude everything
        .pipe($.useref({ noconcat: true
                        ,allowEmpty: true } ))
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.cache, map: fileNameToMin}) ))
        .pipe($.if('!*.html', $.sourcemaps.init({loadMaps: true})))
        .pipe($.if('*.css', $.postcss(plugins) ))
        .pipe($.if('*.js', $.uglifyEs.default({output: {comments:false} })))
        .pipe($.rename(function (path) {
                if(path.extname === '.js' || path.extname === '.css') {
                    path.basename += '.min';
                } }))
        .pipe($.sourcemaps.write('maps'))
        .pipe($.if('!*.html', $.if(!mode.force(), $.size({showFiles: true, total: true}))))
        .pipe($.if(['**/*.js','**/*.css', '**/*.map'], gulp.dest(pkg.app.cache)) )
        .on('end', done);
});

gulp.task('maps', function (done) {
    return gulp.src(pkg.app.cache+'/maps/**/*.map', {base:pkg.app.cache})
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dest}) ))
        // .pipe($.size({showFiles: true, total: true}))
        .pipe(gulp.dest(pkg.app.dest));
});

gulp.task('bundle', gulp.series([`cache`, `maps`],function (done) {
    return gulp.src(pkg.app.src+'/index.html')
        // process everything; remove the lines with BUNDLE_TAG
        .pipe($.replace(/.*BUNDLE_TAG.*/g, ""))
        .pipe($.useref( { // transform name to .min version
                          transformPath:fileNameToMin 
                          // use add path the location with the min versions
                         ,searchPath: pkg.app.cache
                         ,allowEmpty: true }))
        .pipe($.if(['**/*.min.js', '**/*.min.css'], $.header(banner, {pkg: pkg}) ))
        .pipe($.size({showFiles: true, total: true}))
        // don't write the patched html file
        .pipe($.if('!*.html', gulp.dest(pkg.app.dest))) 
        .on('end', done);
}));

gulp.task('genindex', function(done){
    return gulp.src(pkg.app.src+'/index.html')
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dest})))
        .pipe($.useref({noAssets: true}))
        .pipe($.replaceTask({ patterns: REPLACEMENT_PATTERNS }))
        .pipe($.preprocess({ context: { STRIP_CONFIG: true } }))
        .pipe($.size({showFiles: true, total: true}))
        .pipe(($.if('index.html', gulp.dest(pkg.app.dest))) )
        .on('end', done);
});

gulp.task('patchheader', function (done) {
    return gulp.src(pkg.app.src+'/header.php')
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dist})))
        .pipe($.removeCode({USEBUNDLE:true}))
        .pipe($.replace(/\/\/[ ]USEBUNDLE[ ]/g, ""))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

gulp.task('patchfooter', function (done) {
    return gulp.src(pkg.app.src+'/footer.php')
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dist})))
        .pipe($.removeCode({USEBUNDLE:true, commentStart: "<!--", commentEnd:"-->"}))
        .pipe($.htmlmin({ collapseWhitespace: true,
            ignoreCustomFragments: [ /<%[\s\S]*?%>/, /<\?[=|php]?[\s\S]*?\?>/ ] 
        }))
        .pipe($.rename(function (path) {
            path.basename += '.min';
         }))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

gulp.task('minifyhtml', function (done) {
    return gulp.src(pkg.app.src+'/templates/indextpl.html')
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dist})))
        .pipe($.htmlmin({ collapseWhitespace: true,
            ignoreCustomFragments: [ /<%[\s\S]*?%>/, /<\?[=|php]?[\s\S]*?\?>/ ] 
        }))
        .pipe($.rename(function (path) {
            path.basename += '.min';
         }))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

gulp.task('artwork', function(done) {
    gulp.src([ pkg.app.src+'/webfonts/**/*'
              ,pkg.app.src+'/fonts/**/*'
              ,pkg.app.src+'/images/**/*' ], {base:pkg.app.src})
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dest})))
//        .pipe($.size({showFiles: true, total: true}))
        .pipe(gulp.dest(pkg.app.dest));
    done();
});

gulp.task('clean', function(done) {
    if(mode.all() )
        return $.del([pkg.app.dest,pkg.app.dist, pkg.app.cache]);
    else
        return $.del([pkg.app.dest,pkg.app.dist]);
});

gulp.task('build', gulp.series( [`sass`, `bundle`, `genindex`, `artwork`], function (done) {        
    done();
}));

gulp.task('deployback', gulp.series(['patchheader','patchfooter', 'minifyhtml'], function (done) {
    return gulp.src([  pkg.app.src+'/*.php'
                      ,pkg.app.src+'/command/**/*'
                      ,pkg.app.src+'/inc/**/*'
                      ,pkg.app.src+'/templates/**/*'
                      ,pkg.app.src+'/*'
                      ,'!'+pkg.app.src+'/index.html'
                      //,'!'+pkg.app.src+'/index.php'
                      ,'!'+pkg.app.src+'/header.php'
                      ,'!'+pkg.app.src+'/footer.php'
                      ,'!'+pkg.app.src+'/footer.min.php'
                      ,'!'+pkg.app.src+'/templates/indextpl.html'],

                      {base: pkg.app.src})
        // optional headers fields can be update and or added:
        //.pipe( $.replaceTask({ patterns: REPLACEMENT_PATTERNS }))
        //.pipe($.if('*.html', $.header(banner_html, {pkg: pkg}) ))
        .pipe($.if(!mode.test(), $.chown('root','root')))
        .pipe($.if(!mode.force(), $.newer( { dest: DEPLOY_LOCATION})))
        //.pipe($.size({showFiles: true, total: true}))
        .pipe($.if('*.html', $.replaceTask({ patterns: REPLACEMENT_PATTERNS})))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
}));

gulp.task('deployfront', function (done) {    
    return gulp.src( [pkg.app.dest+'/**/*', '!'+pkg.app.dest+'/index.html'] )
        .pipe($.if(!mode.force(), $.newer( { dest: DEPLOY_LOCATION})))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

gulp.task('deploy', gulp.series( [`build`, `deployfront`, `deployback`],function (done) {
//gulp.task('deploy', gulp.series( [`deployfront`, `deployback`], function (done) {
    done();
}));

var watchTasks = mode.build() ? ['build', 'browserSync']: ['sass', 'browserSync'],
    triggerTasks = mode.build() ? ['build', 'browserReload']: ['sass', 'browserReload'];
gulp.task('watch',  gulp.series( watchTasks, function (done){
    gulp.watch(pkg.app.src+'/scss/**/*.scss', gulp.series(triggerTasks));
    gulp.watch(pkg.app.src+'/css/**/*.css', gulp.series(triggerTasks));
    gulp.watch(pkg.app.src+'/*.html', gulp.series(triggerTasks));
    gulp.watch(pkg.app.src+'/js/**/*.js', gulp.series(triggerTasks))
    gulp.watch(pkg.app.src+'/../gulpfile.js',
    gulp.watch(pkg.app.src+'/../package.json'));
    done();
}));
