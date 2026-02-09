/**
 * Contact Inbox - REST API Test Script (Node.js)
 *
 * This script demonstrates how to test the REST API endpoint
 * from a Node.js application.
 *
 * Installation: npm install axios
 * Usage: node test-rest-api.js "John Doe" "john@example.com" "Hello!"
 */

const https = require('http');
const querystring = require('querystring');

// Configuration
const apiUrl = 'http://wpdev.local/wp-json/contactin/v1/submit';

// Get parameters from command line or use defaults
const name = process.argv[2] || 'John Doe';
const email = process.argv[3] || 'john@example.com';
const message = process.argv[4] || 'Test message from Node.js script';
const subject = process.argv[5] || 'External Test Submission';

console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('REST API Test - Contact Inbox (Node.js)');
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

console.log(`Endpoint: ${apiUrl}`);
console.log(`Name: ${name}`);
console.log(`Email: ${email}`);
console.log(`Message: ${message}`);
console.log(`Subject: ${subject}\n`);

// Prepare payload
const payload = JSON.stringify({
    name,
    email,
    message,
    subject,
});

// Parse URL
const url = new URL(apiUrl);
const options = {
    hostname: url.hostname,
    port: url.port || 80,
    path: url.pathname + url.search,
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Content-Length': payload.length,
    },
};

// Make the request
const req = https.request(options, (res) => {
    console.log(`HTTP Status: ${res.statusCode}\n`);
    
    let data = '';
    res.on('data', (chunk) => {
        data += chunk;
    });

    res.on('end', () => {
        try {
            const json = JSON.parse(data);
            console.log('Response:');
            console.log(JSON.stringify(json, null, 2));
        } catch (e) {
            console.log('Response:');
            console.log(data);
        }
        console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    });
});

req.on('error', (error) => {
    console.error('ERROR:', error.message);
});

req.write(payload);
req.end();
