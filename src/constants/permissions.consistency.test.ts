import { getDeclaredAppPermissionCodes } from './permissions'
import { ROUTE_ACCESS } from './routeAccess'

describe('permissions consistency', () => {
  const declared = new Set(getDeclaredAppPermissionCodes())

  it('ROUTE_ACCESS ne référence que des codes présents dans PERMISSIONS', () => {
    for (const v of Object.values(ROUTE_ACCESS)) {
      const arr = Array.isArray(v) ? [...v] : [v]
      for (const c of arr) {
        expect(declared.has(c)).toBe(true)
      }
    }
  })

  it('expose des codes au format module.action ou module.sous-domaine.action', () => {
    for (const c of declared) {
      expect(c).toMatch(/^[a-z0-9]+(?:[._-][a-z0-9]+)+$/i)
    }
  })
})
