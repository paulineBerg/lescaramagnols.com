#!/usr/bin/env node

import fs from 'node:fs';
import http from 'node:http';
import https from 'node:https';

const proxyHost = process.env.HTTPS_PROXY_HOST ?? '127.0.0.1';
const proxyPort = Number.parseInt(process.env.HTTPS_PROXY_PORT ?? '8443', 10);
const targetHost = process.env.HTTPS_TARGET_HOST ?? '127.0.0.1';
const targetPort = Number.parseInt(process.env.HTTPS_TARGET_PORT ?? '8000', 10);
const certPath = process.env.HTTPS_CERT_PATH ?? '';
const keyPath = process.env.HTTPS_KEY_PATH ?? '';

if (!Number.isInteger(proxyPort) || proxyPort < 1 || proxyPort > 65535) {
    throw new Error(`HTTPS_PROXY_PORT invalide: ${process.env.HTTPS_PROXY_PORT ?? ''}`);
}

if (!Number.isInteger(targetPort) || targetPort < 1 || targetPort > 65535) {
    throw new Error(`HTTPS_TARGET_PORT invalide: ${process.env.HTTPS_TARGET_PORT ?? ''}`);
}

if (certPath === '' || keyPath === '') {
    throw new Error('HTTPS_CERT_PATH et HTTPS_KEY_PATH sont obligatoires pour le proxy HTTPS local.');
}

const cert = fs.readFileSync(certPath);
const key = fs.readFileSync(keyPath);

const server = https.createServer({ cert, key }, (req, res) => {
    const incomingHost = req.headers.host ?? `${proxyHost}:${proxyPort}`;
    const proxyHeaders = {
        ...req.headers,
        host: `${targetHost}:${targetPort}`,
        'x-forwarded-proto': 'https',
        'x-forwarded-host': incomingHost,
        'x-forwarded-port': String(proxyPort),
    };

    const proxyReq = http.request(
        {
            host: targetHost,
            port: targetPort,
            method: req.method,
            path: req.url,
            headers: proxyHeaders,
        },
        (proxyRes) => {
            res.writeHead(proxyRes.statusCode ?? 502, proxyRes.headers);
            proxyRes.pipe(res);
        }
    );

    proxyReq.on('error', (error) => {
        res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end(`Proxy HTTPS local indisponible: ${error.message}`);
    });

    req.pipe(proxyReq);
});

server.on('listening', () => {
    process.stdout.write(
        `Proxy HTTPS local actif: https://${proxyHost}:${proxyPort} -> http://${targetHost}:${targetPort}\n`
    );
});

server.listen(proxyPort, proxyHost);
