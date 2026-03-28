/**
 * Verifie que chaque code dans @RequirePermission('...') existe dans PERMISSIONS (front).
 * Usage: node scripts/check-permission-parity.mjs (depuis la racine du depot)
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.join(__dirname, '..')

function walkTsFiles(dir, acc = []) {
  for (const name of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, name.name)
    if (name.isDirectory()) {
      if (name.name === 'node_modules' || name.name === 'dist') continue
      walkTsFiles(p, acc)
    } else if (name.isFile() && name.name.endsWith('.ts')) {
      acc.push(p)
    }
  }
  return acc
}

function extractPermissionsBlock(src) {
  const start = src.indexOf('export const PERMISSIONS')
  if (start === -1) return null
  const end = src.indexOf('} as const', start)
  if (end === -1) return null
  return src.slice(start, end + '} as const'.length)
}

function declaredCodesFromFrontend() {
  const permPath = path.join(root, 'src', 'constants', 'permissions.ts')
  const text = fs.readFileSync(permPath, 'utf8')
  const block = extractPermissionsBlock(text)
  if (!block) {
    console.error('Bloc PERMISSIONS introuvable')
    process.exit(1)
  }
  const codes = new Set()
  for (const m of block.matchAll(/'([a-z0-9._-]+)'/gi)) {
    const c = m[1]
    if (c.includes('.')) codes.add(c)
  }
  return codes
}

function codesUsedInBackendGuards() {
  const backendSrc = path.join(root, 'backend', 'src')
  if (!fs.existsSync(backendSrc)) {
    console.error('backend/src introuvable')
    process.exit(1)
  }
  const files = walkTsFiles(backendSrc)
  const used = new Set()
  for (const file of files) {
    const text = fs.readFileSync(file, 'utf8')
    const lines = text.split('\n')
    for (const line of lines) {
      if (!line.includes('RequirePermission')) continue
      const t = line.trim()
      if (t.startsWith('//') || t.startsWith('*')) continue
      for (const m of line.matchAll(/'([a-z][a-z0-9._-]*)'/g)) {
        const c = m[1]
        if (!c.includes('.')) continue
        used.add(c)
      }
    }
  }
  return used
}

const declared = declaredCodesFromFrontend()
const used = codesUsedInBackendGuards()
const missing = [...used].filter((c) => !declared.has(c)).sort()

if (missing.length > 0) {
  console.error('[check-permission-parity] Manquants dans PERMISSIONS:')
  for (const c of missing) console.error('  ', c)
  process.exit(1)
}

console.log(
  '[check-permission-parity] OK —',
  used.size,
  'codes guards,',
  declared.size,
  'codes declares.',
)
