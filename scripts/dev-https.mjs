import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import http from 'node:http'
import https from 'node:https'
import net from 'node:net'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const certDir = path.join(root, 'certs')
const certPath = path.join(certDir, 'dev.crt')
const keyPath = path.join(certDir, 'dev.key')
const listenPort = 8443
const phpPort = 8010
const vitePort = 5173
const viteOrigin = `http://127.0.0.1:${vitePort}`
const publicOrigin = `https://192.168.68.106:${listenPort}`

if (!fs.existsSync(certPath) || !fs.existsSync(keyPath)) {
  fs.mkdirSync(certDir, { recursive: true })
  execFileSync('openssl', [
    'req', '-x509', '-newkey', 'rsa:2048',
    '-keyout', keyPath,
    '-out', certPath,
    '-days', '825',
    '-nodes',
    '-subj', '/CN=192.168.68.106',
    '-addext', 'subjectAltName=DNS:localhost,IP:127.0.0.1,IP:192.168.68.106',
  ], { stdio: 'inherit' })
}

function isVite(url = '/') {
  const pathOnly = url.split('?')[0]
  return pathOnly.startsWith('/@')
    || pathOnly.startsWith('/resources/')
    || pathOnly.startsWith('/node_modules/')
    || pathOnly.startsWith('/__vite')
}

const server = https.createServer(
  {
    key: fs.readFileSync(keyPath),
    cert: fs.readFileSync(certPath),
  },
  (req, res) => {
    const vite = isVite(req.url)
    const headers = { ...req.headers }
    headers['x-forwarded-proto'] = 'https'
    headers['x-forwarded-host'] = req.headers.host
    headers.host = vite ? `127.0.0.1:${vitePort}` : `127.0.0.1:${phpPort}`

    const upstream = http.request({
      hostname: '127.0.0.1',
      port: vite ? vitePort : phpPort,
      path: req.url,
      method: req.method,
      headers,
    }, (proxied) => {
      const type = String(proxied.headers['content-type'] || '')
      const rewrite = /text\/html|javascript|ecmascript|text\/css|json/.test(type)
      if (!rewrite) {
        res.writeHead(proxied.statusCode || 502, proxied.headers)
        proxied.pipe(res)
        return
      }
      const chunks = []
      proxied.on('data', (chunk) => chunks.push(chunk))
      proxied.on('end', () => {
        const body = Buffer.concat(chunks).toString('utf8').replaceAll(viteOrigin, publicOrigin)
        const headersOut = { ...proxied.headers }
        delete headersOut['content-length']
        delete headersOut['content-encoding']
        res.writeHead(proxied.statusCode || 502, headersOut)
        res.end(body)
      })
    })
    upstream.setTimeout(0)
    upstream.on('error', (error) => {
      if (!res.headersSent) res.writeHead(502)
      res.end(error.message)
    })
    req.pipe(upstream)
  },
)

server.on('upgrade', (req, socket, head) => {
  const upstream = net.connect(vitePort, '127.0.0.1', () => {
    const headers = { ...req.headers, host: `127.0.0.1:${vitePort}` }
    const lines = [`${req.method} ${req.url} HTTP/1.1`]
    for (const [name, value] of Object.entries(headers)) {
      if (value === undefined) continue
      lines.push(`${name}: ${Array.isArray(value) ? value.join(', ') : value}`)
    }
    upstream.write(`${lines.join('\r\n')}\r\n\r\n`)
    if (head.length) upstream.write(head)
    socket.pipe(upstream)
    upstream.pipe(socket)
  })
  const drop = () => {
    socket.destroy()
    upstream.destroy()
  }
  upstream.on('error', drop)
  socket.on('error', drop)
})

server.listen(listenPort, '0.0.0.0', () => {
  console.log(`https://192.168.68.106:${listenPort}`)
})
