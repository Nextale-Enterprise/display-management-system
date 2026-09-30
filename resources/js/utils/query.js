import { useCommonStore } from '@/store/common'

export function scopedQuery(extra = []) {
  const common = useCommonStore()
  const queries = [...extra]
  if (common.shouldScopeToSelectedOrganization) {
    queries.push({
      field: 'organization',
      value: common.organizationSelected,
    })
  }

  return {
    'queries[]': queries.map(query => JSON.stringify(query)),
    organization_id: common.shouldScopeToSelectedOrganization ? common.organizationSelected : undefined,
  }
}
