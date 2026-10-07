import net from 'node:net'

const listenHost = '0.0.0.0'
const listenPort = 8012
const targetHost = '127.0.0.1'
const targetPort = 8010

const server = net.createServer((client) => {
  const upstream = net.connect(targetPort, targetHost)
  const close = () => {
    client.destroy()
    upstream.destroy()
  }
  client.on('error', close)
  upstream.on('error', close)
  client.pipe(upstream)
  upstream.pipe(client)
})

server.listen(listenPort, listenHost, () => {
  console.log(`listen ${listenHost}:${listenPort} -> ${targetHost}:${targetPort}`)
})
