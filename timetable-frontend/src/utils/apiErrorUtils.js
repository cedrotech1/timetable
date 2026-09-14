/** Read a useful message from axios error payloads (JSON, blob, or text). */
export async function readApiErrorMessage(error, fallback = 'Request failed') {
  const data = error?.response?.data;
  if (!data) {
    return error?.message || fallback;
  }

  if (typeof data === 'object' && !(data instanceof Blob)) {
    return data.message || data.error || fallback;
  }

  if (data instanceof Blob) {
    try {
      const text = (await data.text()).trim();
      if (!text) return fallback;
      if (text.startsWith('{') || text.startsWith('[')) {
        try {
          const parsed = JSON.parse(text);
          return parsed.message || parsed.error || fallback;
        } catch {
          /* not JSON */
        }
      }
      if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
        return 'Server returned an HTML page instead of JSON. The API may be down or not updated yet.';
      }
      if (text.startsWith('#')) {
        return 'Server returned an unexpected response. Check that the latest backend is deployed and running.';
      }
      return text.length > 240 ? `${text.slice(0, 240)}…` : text;
    } catch {
      return fallback;
    }
  }

  if (typeof data === 'string') {
    const text = data.trim();
    if (text.startsWith('{')) {
      try {
        const parsed = JSON.parse(text);
        return parsed.message || parsed.error || fallback;
      } catch {
        return text || fallback;
      }
    }
    return text || fallback;
  }

  return fallback;
}

/** True when a blob looks like a ZIP file (PK header). */
export async function blobLooksLikeZip(blob) {
  if (!(blob instanceof Blob)) return false;
  const header = new Uint8Array(await blob.slice(0, 2).arrayBuffer());
  return header[0] === 0x50 && header[1] === 0x4b;
}
