export function normalizePhoneForWhatsApp(input: string): string {
  // WhatsApp wa.me attend un numéro au format international sans '+' ni espaces
  // Ex: "+225 07 00 00 00 00" -> "2250700000000"
  return (input ?? '').replace(/[^\d]/g, '')
}

export function buildWhatsAppChatUrl(phone: string, message?: string): string | null {
  const normalized = normalizePhoneForWhatsApp(phone)
  if (!normalized) return null
  const base = `https://wa.me/${normalized}`
  if (!message?.trim()) return base
  return `${base}?text=${encodeURIComponent(message)}`
}

