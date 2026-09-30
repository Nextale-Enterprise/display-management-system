import { addCollection, Icon as IconifyIcon } from '@iconify/vue'
import { h } from 'vue'
import tablerIcons from './tabler-subset.json'

// Subset of @iconify-json/tabler. Regenerate from that package when a new tabler-* name is added.
addCollection(tablerIcons)

const aliases = {
  collapse: 'tabler-chevron-up',
  complete: 'tabler-check',
  cancel: 'tabler-x',
  close: 'tabler-x',
  delete: 'tabler-circle-x-filled',
  clear: 'tabler-circle-x',
  success: 'tabler-circle-check',
  info: 'tabler-info-circle',
  warning: 'tabler-alert-triangle',
  error: 'tabler-alert-circle',
  prev: 'tabler-chevron-left',
  next: 'tabler-chevron-right',
  checkboxOn: 'tabler-square-check-filled',
  checkboxOff: 'tabler-square',
  checkboxIndeterminate: 'tabler-square-minus-filled',
  delimiter: 'tabler-circle',
  sort: 'tabler-arrow-up',
  expand: 'tabler-chevron-down',
  menu: 'tabler-menu-2',
  subgroup: 'tabler-caret-down',
  dropdown: 'tabler-chevron-down',
  edit: 'tabler-pencil',
  loading: 'tabler-refresh',
  first: 'tabler-chevrons-left',
  last: 'tabler-chevrons-right',
  unfold: 'tabler-arrows-move-vertical',
  file: 'tabler-paperclip',
  plus: 'tabler-plus',
  minus: 'tabler-minus',
  sortAsc: 'tabler-arrow-up',
  sortDesc: 'tabler-arrow-down',
  radioOn: 'tabler-circle-dot-filled',
  radioOff: 'tabler-circle',
}

function iconName(icon) {
  if (typeof icon !== 'string' || icon.includes(':')) return icon
  if (icon.startsWith('tabler-')) return `tabler:${icon.slice('tabler-'.length)}`
  return icon
}

export const icons = {
  defaultSet: 'iconify',
  aliases,
  sets: {
    iconify: {
      component: props => h(IconifyIcon, {
        icon: iconName(props.icon),
        class: props.class,
        style: props.style,
        width: '1em',
        height: '1em',
      }),
    },
  },
}
