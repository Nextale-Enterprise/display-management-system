<script setup>
import { computed, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { useBranchStore } from '@/store/branch'
import { useCommonStore } from '@/store/common'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'

const store = useBranchStore()
const common = useCommonStore()
const toast = useToast()
const showModal = ref(false)
const editingId = ref(null)
const filterName = ref('')
const formError = ref('')

const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
})
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { name: '' },
})
const { value: name } = useField('name')

onMounted(() => store.refreshList())

function openCreate() {
  editingId.value = null
  formError.value = ''
  resetForm({ values: { name: '' } })
  showModal.value = true
}

function openEdit(item) {
  editingId.value = item.id
  formError.value = ''
  resetForm({ values: { name: item.name } })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  const wasEditing = Boolean(editingId.value)
  try {
    if (editingId.value) await store.update({ id: editingId.value, name: values.name })
    else await store.create({ name: values.name })
    showModal.value = false
    toast.success(wasEditing ? 'Branch updated' : 'Branch created')
  } catch (error) {
    formError.value = errorText(error, 'Could not save the branch.')
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

async function remove(item) {
  const accepted = await confirm({
    title: 'Delete branch',
    text: `Delete ${item.name}? Devices in this branch stay, without a branch.`,
    confirmText: 'Delete',
    confirmColor: 'error',
  })
  if (!accepted) return
  try {
    await store.remove(item.id)
    toast.success('Branch deleted')
  } catch (error) {
    toast.error(errorText(error, 'Could not delete the branch.'))
  }
}

const subscriptionLine = computed(() => {
  const org = common.organizations.find(item => String(item.id) === String(common.organizationSelected))
  if (!org) return ''
  const branches = org.branch_limit == null ? `${org.branches_count} branches, no limit` : `${org.branches_count} / ${org.branch_limit} branches`
  const period = org.subscription_ends_at ? `until ${org.subscription_ends_at}` : 'no end date'
  return `${branches}, ${period}`
})
</script>

<template>
  <div>
  <p v-if="subscriptionLine" class="text-body-2 text-medium-emphasis mb-3">{{ subscriptionLine }}</p>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Branch"
    filter-placeholder="Enter branch name"
    :loading="store.getIsLoading"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <AmsDataTable
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[{ title: 'Action', key: 'actions', sortable: false, width: 120 }, { title: 'Name', key: 'name' }]"
    >
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn color="primary" aria-label="Edit" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
            <VTooltip activator="parent">Edit</VTooltip>
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
          <VCardTitle class="text-h5 pt-6 px-6">{{ editingId ? 'Edit Branch' : 'Add Branch' }}</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <VForm @submit.prevent="submit">
              <label>Name:</label>
              <VTextField v-model="name" placeholder="Enter branch name" :error-messages="errors.name" />
            </VForm>
          </VCardText>
          <VCardActions class="px-6 pb-6">
            <VSpacer />
            <VBtn variant="outlined" @click="showModal = false">Cancel</VBtn>
            <VBtn variant="elevated" @click="submit">{{ editingId ? 'Save' : 'Create' }}</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </AmsListPage>
  </div>
</template>
