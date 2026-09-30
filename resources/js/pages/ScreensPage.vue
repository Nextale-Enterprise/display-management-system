<script setup>
import { onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import * as yup from 'yup'
import { useScreenStore } from '@/store/screen'
import { usePlaylistStore } from '@/store/playlist'
import AmsListPage from '@/components/AmsListPage.vue'

const store = useScreenStore()
const playlists = usePlaylistStore()
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
  try {
    if (editing.value) {
      await store.update({ id: editing.value.id, name: values.name, playlist_id: values.playlist_id || null })
    } else {
      await store.create({ name: values.name })
    }
    showModal.value = false
  } catch (error) {
    formError.value = error?.data?.message || 'Could not save the screen.'
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

function seen(value) {
  return value ? new Date(value).toLocaleString() : 'never'
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Screen"
    filter-placeholder="Enter screen name"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <v-data-table
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Pairing code', key: 'pairing_code' },
        { title: 'Playlist', key: 'playlist_name' },
        { title: 'Last seen', key: 'last_seen_at' },
        { title: '', key: 'actions', sortable: false },
      ]"
    >
      <template #item.last_seen_at="{ item }">{{ seen(item.last_seen_at) }}</template>
      <template #item.actions="{ item }">
        <v-btn variant="outlined" color="primary" icon="mdi-pencil" size="small" class="me-2" @click="openEdit(item)" />
        <v-btn variant="outlined" size="small" class="me-2" @click="store.regenerate(item.id)">New code</v-btn>
        <v-btn variant="outlined" color="error" icon="mdi-delete" size="small" @click="store.remove(item.id)" />
      </template>
    </v-data-table>
    <template #dialog>
      <v-dialog v-model="showModal" max-width="600" persistent>
        <v-card>
          <v-card-title>{{ editing ? 'Edit Screen' : 'Add Screen' }}</v-card-title>
          <v-card-text>
            <v-alert v-if="formError" type="error" class="mb-3" :text="formError" />
            <label>Name:</label>
            <v-text-field v-model="name" placeholder="Enter screen name" :error-messages="errors.name" />
            <template v-if="editing">
              <label>Playlist:</label>
              <v-select
                v-model="playlistId"
                :items="playlists.getList"
                item-title="name"
                item-value="id"
                clearable
              />
            </template>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="outlined" @click="showModal = false">Cancel</v-btn>
            <v-btn variant="elevated" color="primary" @click="submit">{{ editing ? 'Save' : 'Create' }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>
  </AmsListPage>
</template>
