var http = require("http"),  
    url  = require("url"),
    path = require("path");
    fs   = require("fs"),
    mimi = require("./mimi.js").mimi;

mimi.get('/', function(request, response) {
    response.end('hello world');
});

mimi.get('/randomNumber', function(request, response) {
    var a = new String( Math.random() ).toString();
    response.end(a);
});

mimi.get('/article/([^/]+)', function(request, response) {
    var pathname = url.parse(request.url)['pathname'];
    var re = new RegExp('^/article/([^/]+)$');
    var matches = pathname.match(re);

    response.write(matches[1] + ' hello');
    response.end();

});

mimi.post('/upload', function(request, response) {
    response.end();
}

mimi.run(9000);




