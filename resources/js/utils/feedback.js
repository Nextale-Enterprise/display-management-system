export function errorText(error, fallback) {
  const data = error?.data || error?._data
  if (typeof data?.message === 'string' && data.message !== '') return data.message
  if (typeof data?.error === 'string' && data.error !== '') return data.error
  return fallback
}
