var http = require("http"), 
    url  = require("url"),
    path = require("path");
    fs   = require("fs");

var mimi = (function() {

    var getRouteMap  = [];
    var postRouteMap = [];

    var throw404 = function(request, response) {
        response.writeHead(404, 'Not Found');
        response.write('<h1>404 Not Found</h1><p>you request url(' + request.url + ') is not found!</p>');
        response.end();

    }

    var get = function(route, callback) {
        getRouteMap.push([new RegExp('^' + route + '$'), callback]);
    }

    var post = function(route, callback) {
        postRouteMap.push([new RegExp('^' + route + '$'), callback]);
    }


    var staticFileProcessor = function(request, response) {

        var pathname = url.parse(request.url)['pathname'];
        var realpath = ['public', pathname].join('/');
        realpath     = path.normalize(realpath);

        if(realpath.substr(0,6) !== 'public') {
            throw404(request, response);
            return false;
        }

        //console.log(realpath);

        if( fs.existsSync(realpath) ) {
            fs.readFile(realpath, "binary", function(err, file) {
                if (err) {
                    response.writeHead(403, 'Forbidden');
                    response.end('<h1> 403 Forbidden </h1>');
                } else {
                    response.write(file, 'binary')
                    response.end();
                }
             });
        } else {
            throw404(request, response);
        }

    }

    var server = http.createServer(function(request, response) {

        var method   = request.method;
        var pathname = url.parse(request.url)['pathname'];
        var bingo    = false;

        response.setHeader('Server', 'Microsoft-IIS/7.5');
        response.setHeader('X-Powered-By', 'PHP/5.3.6');

        switch(method) {
            case 'GET' :

                for(var i = 0; i < getRouteMap.length; i++) {
                if(getRouteMap[i][0].test(pathname)) {
                    getRouteMap[i][1](request, response);
                    bingo = true;
                    break;
                }
                }

                break;
            case 'POST' :

                for(var i = 0; i < postRouteMap.length; i++) {
                if(postRouteMap[i][0].test(pathname)) {
                    postRouteMap[i][1](request, response);
                    bingo = true;
                    break;
                }
                }

                break;

            default :
                break;
        }

        if(!bingo && method === 'GET') {
            staticFileProcessor(request, response);
        }
        else if(!bingo) {
            throw404(request, response);
        }
    });

    var run = function() {

        if(!arguments[0]) {
            var port = Math.floor(Math.random() * (65535 - 1024));
        } else {
            var port = parseInt(arguments[0], 10);
        }

        server.listen(port);

        console.log('Http Server is running on port ' + port);
    }

    
    return {
        run : run,
        get : get,
        post : post
    };

})();

exports.mimi = mimi;
