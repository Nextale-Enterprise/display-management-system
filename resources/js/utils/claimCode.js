export function parseClaimCode(raw) {
  const text = String(raw || '').trim()
  if (!text) return ''
  const fromUrl = text.match(/claim\/([A-Za-z0-9]+)/i)
  const source = fromUrl ? fromUrl[1] : text
  return source.toUpperCase().replace(/[^A-Z0-9]/g, '')
}
