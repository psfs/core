'use strict';
var config = {};
var channel;
var http;

function sendMessage(message, data, arrBuff) {
    if(channel) {
        channel.postMessage([message || 'check', data || {}], arrBuff ? [arrBuff] : null);
    }
}

function parseError(error) {
    debugger;
}

self.onmessage = function(event) {
    var command = Array.isArray(event.data) ? event.data[0] : 'unknown',
        data = Array.isArray(event.data) ? event.data[1] : event.data;
    switch(command) {
        case 'setup':
            config = data;
            if('imports' in config && Array.isArray(config['imports'])) {
                for(let i in config['imports']) {
                    importScripts(config['imports'][i]);
                }
            }
            if(event.ports.length) {
                channel = event.ports[0];
                channel.onmessageerror = parseError;
            }
            sendMessage();
            break;
        case 'ping':
            sendMessage('pong');
            break;
        case 'unsubscribe':
            channel = null;
            config = {};
            break;
        case 'get':
            debugger;
            //config.http.$get(data[])
            break;
        case 'get.response':
            sendMessage(btoa(event.data[2]), data);
            break;
        default:
            console.log(command);
            console.info(data);
            break;
    }
};