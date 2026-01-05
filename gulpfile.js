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
 *              [--all]           Also deploy moodeutl and the /var/local/www dir
 *              [--remote]        Deploy to target Pi by using ssh/scp
 *
 *                                Deploy only copy/update, never removes files.
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

// used for remote deployment
var gulpSSH = new $.ssh({
    ignoreErrors: false,
    sshConfig: {
        host: pkg.remote.host,
        port: 22,
        username: pkg.remote.user,
        password: pkg.remote.password
      }
});

// configure the gulp mode options
const mode = $.mode( {  modes: ["build", "development", "test", "all", "force", "remote"],
                        default: "development",
                        verbose: false});

const DEPLOY_LOCATION = (mode.test() || mode.remote() ) ? pkg.app.dist: pkg.app.deploy;

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
            $.httpProxyMiddleware.createProxyMiddleware('/imagesw/bgimage.jpg',  { target: pkg.server.proxy, changeOrigin: true }),
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

/**
 * Cache create from used js and css resources minified and sourcemaps files.
 *
 */
gulp.task('cache', function(done){
    var plugins=[
        autoprefixer({cascade: false}),
        cssnano()
    ];
    return gulp.src(pkg.app.src+'/header.php')
        .pipe($.rename(function (path) {
            path.basename = 'index';
            path.extname = '.html';
        }))
        .pipe($.replace(/[.]min[.]css\"/g, ".css\"")) // make sure no minified css is uses as source
        .pipe($.replace(/[.]min[.]js\"/g, ".js\""))  // make sure no minified js is uses as source
        .pipe($.replace(/.*BUNDLE_TAG.*/g, "")) // remove comment blocks to include everything
        .pipe($.replace(/.*CONFIGBLOCKSECTION.*/g, ""))
        .pipe($.replace(/.*GEN_DEV_INDEX_TAG.*/g, ""))
        .pipe($.removeCode({USEBUNDLE:true, GENINDEXDEV:true, commentStart: "<!--", commentEnd:"-->"}))
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
//        .pipe($.if('*.html',$.size({showFiles: true, total: true})))
//        .pipe($.if('*.html', gulp.dest(pkg.app.cache)) )
        .on('end', done);
});

gulp.task('maps', function (done) {
    return gulp.src(pkg.app.cache+'/maps/**/*.map', {base:pkg.app.cache})
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dest}) ))
        // .pipe($.size({showFiles: true, total: true}))
        .pipe(gulp.dest(pkg.app.dest));
});

/**
 * Generate the bundle files for css and js, excluding the html file.
 * It reuse the minified files in the cache
 */
gulp.task('bundle', gulp.series([`cache`, `maps`],function (done) {
    return gulp.src(pkg.app.src+'/header.php')
        .pipe($.rename(function (path) {
            path.basename = 'index';
            path.extname = '.html';
        }))
        .pipe($.replace(/[.]min[.]css\"/g, ".css\"")) // make sure no minified css is uses as source
        .pipe($.replace(/[.]min[.]js\"/g, ".js\""))  // make sure no minified js is uses as source
        .pipe($.replace(/.*BUNDLE_TAG.*/g, "")) // remove comment blocks to in clude everything
        .pipe($.replace(/.*CONFIGBLOCKSECTION.*/g, ""))
        .pipe($.replace(/.*GEN_DEV_INDEX_TAG.*/g, ""))
        .pipe($.removeCode({USEBUNDLE:true, GENINDEXDEV:true, commentStart: "<!--", commentEnd:"-->"}))
        .pipe($.useref( { // transform name to .min version
                          transformPath:fileNameToMin
                          // use add path the location with the min versions
                         ,searchPath: pkg.app.cache
                         ,allowEmpty: true }, $.lazypipe().pipe($.sourcemaps.init, { loadMaps: true })))
        .pipe($.if(['**/*.min.js', '**/*.min.css'], $.header(banner, {pkg: pkg}) ))
        .pipe($.size({showFiles: true, total: true}))
        .pipe($.sourcemaps.write('maps'))
        // don't write the patched html file
        .pipe($.if('!*.html', gulp.dest(pkg.app.dest)))
        .on('end', done);
}));

/**
 * During frontend development based on gulp we cannot use index.php.
 * An alternative index.html has to be generated. It should contain the content of:
 *  - header.php
 *  - templates/indextpl.html
 *  - footer.php
 *
 * It uses header.php with processing tage to create the src/index.html. This one includes the (parts of the ) resources.
 *
 */

gulp.task('genindexdev', function(done){
    return gulp.src(pkg.app.src+'/header.php')
        .pipe($.rename(function (path) {
            path.basename = 'index';
            path.extname = '.html';
        }))
        .pipe($.replace(/[.]min[.]css\"/g, ".css\"")) // make sure no minified css is uses as source
        .pipe($.replace(/[.]min[.]js\"/g, ".js\""))  // make sure no minified js is uses as source
        .pipe($.replace(/.*BUNDLE_TAG.*/g, "")) // adds multiple jquery files instead of one jquery bundle
        .pipe($.replace(/.*GEN_DEV_INDEX_TAG.*/g, "")) // make wellformed by adding cloding body and html
        .pipe($.replace(/.*CONFIGBLOCKSECTION_BEGIN.*/g, "<!-- CONFIGBLOCKSECTION")) // adds multiple jquery files instead of one jquery bundle
        .pipe($.replace(/.*CONFIGBLOCKSECTION_END.*/g, "CONFIGBLOCKSECTION -->")) // adds multiple jquery files instead of one jquery bundle
        .pipe($.include({
            hardFail: true,
            separateInputs: true,
            includePaths: [
            __dirname + '/www'
            ]})) // include templates/indextpl.html and footer.php to create working index.html
        .pipe($.removeCode({USEBUNDLE:true, GENINDEXDEV:true, commentStart: "<!--", commentEnd:"-->"}))
        .pipe((gulp.dest(pkg.app.src) ))
        .on('end', done);
});

/**
 * Generates the develop index.html (uses the bundles)
 */
gulp.task('genindex', function(done){
    return gulp.src(pkg.app.src+'/header.php')
        .pipe($.rename(function (path) {
            path.basename = 'index';
            path.extname = '.html';
        }))
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dest})))
        .pipe($.replace(/[.]min[.]css\"/g, ".css\"")) // make sure no minified css is uses as source
        .pipe($.replace(/[.]min[.]js\"/g, ".js\""))  // make sure no minified js is uses as source
        .pipe($.replace(/.*BUNDLE_TAG.*/g, "")) // adds multiple jquery files instead of one jquery bundle
        .pipe($.replace(/.*GEN_DEV_INDEX_TAG.*/g, "")) // make wellformed by adding cloding body and html
        .pipe($.replace(/.*CONFIGBLOCKSECTION_BEGIN.*/g, "<!-- CONFIGBLOCKSECTION")) // adds multiple jquery files instead of one jquery bundle
        .pipe($.replace(/.*CONFIGBLOCKSECTION_END.*/g, "CONFIGBLOCKSECTION -->")) // adds multiple jquery files instead of one jquery bundle
        .pipe($.include({
            hardFail: true,
            separateInputs: true,
            includePaths: [
            __dirname + '/www'
            ]})) // include templates/indextpl.html and footer.php to create working index.html
        .pipe($.removeCode({USEBUNDLE:true, GENINDEXDEV:true, NOCONFIGSECTION:true, commentStart: "<!--", commentEnd:"-->"}))
        .pipe($.useref({noAssets: true}))
        .pipe($.if('index.html', $.cacheBust({
            type: 'timestamp'
            }))  )
        .pipe($.replaceTask({ patterns: REPLACEMENT_PATTERNS }))
        .pipe($.preprocess({ context: { STRIP_CONFIG: true } }))
        .pipe($.size({showFiles: true, total: false}))
        .pipe(($.if('index.html', gulp.dest(pkg.app.dest))) )
        .on('end', done);
});

gulp.task('patchheader', function (done) {
    return gulp.src(pkg.app.src+'/header.php')
        //.pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dist})))
        .pipe($.replace(/[.]min[.]css\"/g, ".css\"")) // make sure no minified css is uses as source
        .pipe($.replace(/[.]min[.]js\"/g, ".js\""))  // make sure no minified js is uses as source
        .pipe($.replace(/.*BUNDLE_TAG.*/g, ""))
        .pipe($.removeCode({ GENINDEXDEV: false, NOCONFIGSECTION: false, GENINDEXDEV: false, USEBUNDLE:true, commentStart: "<!--", commentEnd:"-->"}))
        .pipe($.preprocess({ context: { STRIP_CONFIG: true } }))
        .pipe($.useref({noAssets: true}))
        .pipe($.if('header.php', $.cacheBust({
            type: 'timestamp'
            }))  )
        .pipe($.if(!(mode.test()||mode.remote()), $.chown('root','root')))
        .pipe($.size({showFiles: true, total: false}))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

gulp.task('patchfooter', function (done) {
    return gulp.src(pkg.app.src+'/footer.php')
        //.pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dist})))
        .pipe($.removeCode({USEBUNDLE:true, commentStart: "<!--", commentEnd:"-->"}))
        .pipe($.htmlmin({ collapseWhitespace: true,
            ignoreCustomFragments: [ /<%[\s\S]*?%>/, /<\?[=|php]?[\s\S]*?\?>/ ]
        }))
        .pipe($.rename(function (path) {
            path.basename += '.min';
         }))
        .pipe($.if(!(mode.test()||mode.remote()), $.chown('root','root')))
        .pipe($.size({showFiles: true, total: false}))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

gulp.task('patchindex', function (done) {
    return gulp.src(pkg.app.src+'/index.php')
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dist})))
        .pipe($.replace(/indextpl[.]html/g, "indextpl.min.html"))
        .pipe($.replace(/footer[.]php/g, "footer.min.php"))
        .pipe($.if(!(mode.test()||mode.remote()), $.chown('root','root')))
        .pipe($.size({showFiles: true, total: false}))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

gulp.task('patchconfigs', function (done) {
    return gulp.src(pkg.app.src+'/*-config.php')
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dist})))
        .pipe($.replace(/footer[.]php/g, "footer.min.php"))
        .pipe($.if(!(mode.test()||mode.remote()), $.chown('root','root')))
        .pipe($.size({showFiles: true, total: false}))
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
        .pipe($.if(!(mode.test()||mode.remote()), $.chown('root','root')))
        .pipe($.size({showFiles: true, total: false}))
        .pipe(gulp.dest(DEPLOY_LOCATION+'/templates'))
        .on('end', done);
});

gulp.task('artwork', function(done) {
    gulp.src([ pkg.app.src+'/webfonts/**/*',
               pkg.app.src+'/fonts/**/*',
               pkg.app.src+'/images/**/*' ], {base: pkg.app.src, encoding: false})
        .pipe($.if(!mode.force(), $.newer( { dest: pkg.app.dest })))
//      .pipe($.size({showFiles: true, total: true}))
        .pipe(gulp.dest(pkg.app.dest));
    done();
});

gulp.task('clean', function(done) {
    if(mode.all() )
        return $.del([pkg.app.dest,pkg.app.dist, pkg.app.src+'/index.html', pkg.app.cache]);
    else
        return $.del([pkg.app.dest,pkg.app.dist, pkg.app.src+'/index.html']);
});

gulp.task('build', gulp.series( [`sass`, `genindexdev`, `genindex`, `bundle`, `artwork`], function (done) {
    done();
}));

gulp.task('deploymoodeutl', function (done) {
    return gulp.src([  pkg.app.src+'/../usr/local/bin/moodeutl' ])
        .pipe($.size({showFiles: true, total: true}))
        .pipe(gulp.dest(DEPLOY_LOCATION+'/../../usr/local/bin'))
        .on('end', done);
});

gulp.task('deployvarlocalwww', function (done) {
    return gulp.src([  pkg.app.src+'/../var/local/www/**' ])
        .pipe($.size({showFiles: true, total: true}))
        .pipe(gulp.dest(DEPLOY_LOCATION+'/../local/www'))
        .on('end', done);
});

gulp.task('deployback', gulp.series(['patchheader','patchfooter', 'patchindex', 'patchconfigs', 'minifyhtml'], function (done) {
    return gulp.src([  pkg.app.src+'/*.php'
                      ,pkg.app.src+'/command/**/*'
                      ,pkg.app.src+'/daemon/**/*'
                      ,pkg.app.src+'/inc/**/*'
                      ,pkg.app.src+'/templates/**/*'
                      ,pkg.app.src+'/util/**/*'
                      ,pkg.app.src+'/*'
                      // exclude generated content:
                      ,'!'+pkg.app.src+'/index.html'
                      ,'!'+pkg.app.src+'/templates/indextpl.min.html'
                      ,'!'+pkg.app.src+'/templates/indextpl.html'
                      ,'!'+pkg.app.src+'/header.php'
                      ,'!'+pkg.app.src+'/footer.php'
                      ,'!'+pkg.app.src+'/footer.min.php'
                      ,'!'+pkg.app.src+'/index.php'
                      ,'!'+pkg.app.src+'/*-config.php'
                      ,pkg.app.src+'/css/shellinabox*.css'
                      ],
                      {base: pkg.app.src, encoding: false})
        // optional headers fields can be update and or added:
        //.pipe( $.replaceTask({ patterns: REPLACEMENT_PATTERNS }))
        //.pipe($.if('*.html', $.header(banner_html, {pkg: pkg}) ))
        .pipe($.if(!(mode.test()||mode.remote()), $.chown('root','root')))
        .pipe($.if(!mode.force(), $.newer( { dest: DEPLOY_LOCATION})))
        //.pipe($.size({showFiles: true, total: true}))
        .pipe($.if('*.html', $.replaceTask({ patterns: REPLACEMENT_PATTERNS})))
        .pipe($.chmod(0o755))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
}));

gulp.task('deployfront', function (done) {
    return gulp.src( [pkg.app.dest+'/**/*', '!'+pkg.app.dest+'/index.html'], { encoding: false } )
        .pipe($.if(!mode.force(), $.newer( { dest: DEPLOY_LOCATION})))
        .pipe($.if(!(mode.test()||mode.remote()), $.chown('root','root')))
        .pipe(gulp.dest(DEPLOY_LOCATION))
        .on('end', done);
});

/**
 * upload pkg.app.dist to a temp dir on target device
 */
gulp.task('upload2remote', function (done) {
    return gulp.src(pkg.app.dist+'/**/*')
    .pipe($.scp3({
        host: pkg.remote.host,
        username: pkg.remote.user,
        password: pkg.remote.password,
        dest: '/tmp/www.deploy'}))
    .on('error', function(err) {
        console.log(err);
    })
    .on('end', done);
})

/**
 * Move temp dir on target device to /var/www and activate it
 */

gulp.task('deploy2remote', function (done) {
    return gulpSSH
        .exec(['sudo rm -rf /var/www.prev',
               'sudo mv /var/www/ /var/www.prev',
               'sudo mv /tmp/www.deploy /var/www',
               'sudo chmod -R +x /var/www/command/*',
               'sudo chmod -R +x /var/www/util/*',
               // required if uploaded from windows
               process.platform === "win32" ? 'find /var/www/command/* -exec /usr/bin/dos2unix {} \\; 2>/dev/null': 'echo',
               process.platform === "win32" ? 'find /var/www/util/* -exec /usr/bin/dos2unix {} \\; 2>/dev/null': 'echo',
               '/usr/local/bin/moodeutl -r'
                ], {filePath: 'commands.log'})
        //.pipe(gulp.dest('logs'))
        .on('end', done);
})

var deployTasks = ['deployfront', 'deployback'];
if ( mode.remote() == true ) {
    deployTasks = ['deployfront', 'deployback', 'upload2remote', 'deploy2remote'];
}
else if (mode.all() ==true ){
    deployTasks = ['deployfront', 'deployback', 'deploymoodeutl', 'deployvarlocalwww'];
}

gulp.task('deploy', gulp.series( deployTasks, function (done) {
    done();
}));

var watchTasks = mode.build() ? ['build', 'browserSync']: ['sass', 'genindexdev', 'browserSync'],
    triggerTasks = mode.build() ? ['build', 'browserReload']: ['sass', 'genindexdev', 'browserReload'];
gulp.task('watch',  gulp.series( watchTasks, function (done){
    gulp.watch(pkg.app.src+'/scss/**/*.scss', gulp.series(triggerTasks));
    gulp.watch(pkg.app.src+'/css/**/*.css', gulp.series(triggerTasks));
    //gulp.watch(pkg.app.src+'/*.html', gulp.series(triggerTasks));
    gulp.watch(pkg.app.src+'/js/**/*.js', gulp.series(triggerTasks))
    //gulp.watch(pkg.app.src+'/../gulpfile.js',
    //gulp.watch(pkg.app.src+'/../package.json'));
    done();
}));
