<script setup>
import { onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { useScreenStore } from '@/store/screen'
import { usePlaylistStore } from '@/store/playlist'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'

const store = useScreenStore()
const playlists = usePlaylistStore()
const toast = useToast()
const showModal = ref(false)
const editing = ref(null)
const filterName = ref('')
const formError = ref('')

const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
})
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { name: '', playlist_id: null },
})
const { value: name } = useField('name')
const { value: playlistId } = useField('playlist_id')

onMounted(async () => {
  await Promise.all([store.refreshList(), playlists.refreshList()])
})

function openCreate() {
  editing.value = null
  formError.value = ''
  resetForm({ values: { name: '', playlist_id: null } })
  showModal.value = true
}

function openEdit(item) {
  editing.value = item
  formError.value = ''
  resetForm({ values: { name: item.name, playlist_id: item.playlist_id } })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  const wasEditing = Boolean(editing.value)
  try {
    if (editing.value) {
      await store.update({ id: editing.value.id, name: values.name, playlist_id: values.playlist_id || null })
    } else {
      await store.create({ name: values.name })
    }
    showModal.value = false
    toast.success(wasEditing ? 'Screen updated' : 'Screen created')
  } catch (error) {
    formError.value = errorText(error, 'Could not save the screen.')
  }
})

async function applyFilter() {
  store.query = filterName.value ? [{ field: 'name', value: filterName.value }] : []
  await store.refreshList()
}

async function resetFilter() {
  filterName.value = ''
  store.query = []
  await store.refreshList()
}

async function regenerate(item) {
  try {
    await store.regenerate(item.id)
    toast.success('Pairing code updated')
  } catch (error) {
    toast.error(errorText(error, 'Could not refresh the pairing code.'))
  }
}

async function remove(item) {
  const accepted = await confirm({
    title: 'Delete screen',
    text: `Delete ${item.name}?`,
    confirmText: 'Delete',
    confirmColor: 'error',
  })
  if (!accepted) return
  try {
    await store.remove(item.id)
    toast.success('Screen deleted')
  } catch (error) {
    toast.error(errorText(error, 'Could not delete the screen.'))
  }
}

function seen(value) {
  return value ? new Date(value).toLocaleString() : 'never'
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Screen"
    filter-placeholder="Enter screen name"
    :loading="store.getIsLoading"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <AmsDataTable
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Pairing code', key: 'pairing_code' },
        { title: 'Playlist', key: 'playlist_name' },
        { title: 'Last seen', key: 'last_seen_at' },
        { title: 'Action', key: 'actions', sortable: false, width: 160 },
      ]"
    >
      <template #item.last_seen_at="{ item }">{{ seen(item.last_seen_at) }}</template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn color="primary" aria-label="Edit" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
            <VTooltip activator="parent">Edit</VTooltip>
          </IconBtn>
          <IconBtn color="primary" aria-label="New code" @click="regenerate(item)">
            <VIcon icon="tabler-refresh" />
            <VTooltip activator="parent">New code</VTooltip>
          </IconBtn>
          <IconBtn color="error" aria-label="Delete" @click="remove(item)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Delete</VTooltip>
          </IconBtn>
        </div>
      </template>
    </AmsDataTable>
    <template #dialog>
      <VDialog v-model="showModal">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6">{{ editing ? 'Edit Screen' : 'Add Screen' }}</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <label>Name:</label>
            <VTextField v-model="name" placeholder="Enter screen name" :error-messages="errors.name" />
            <template v-if="editing">
              <label>Playlist:</label>
              <VSelect
                v-model="playlistId"
                :items="playlists.getList"
                item-title="name"
                item-value="id"
                clearable
              />
            </template>
          </VCardText>
          <VCardActions class="px-6 pb-6">
            <VSpacer />
            <VBtn variant="outlined" @click="showModal = false">Cancel</VBtn>
            <VBtn variant="elevated" @click="submit">{{ editing ? 'Save' : 'Create' }}</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </AmsListPage>
</template>
