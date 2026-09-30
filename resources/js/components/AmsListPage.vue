<script setup>
defineProps({
  addLabel: { type: String, required: true },
  filterLabel: { type: String, default: 'Name:' },
  filterPlaceholder: { type: String, default: 'Enter name' },
})

const filter = defineModel('filter', { type: String, default: '' })
const emit = defineEmits(['submit', 'reset', 'add'])
</script>

<template>
  <v-row>
    <v-col cols="12">
      <v-card title="Filter">
        <v-card-item>
          <v-row>
            <v-col md="4">
              <label>{{ filterLabel }}</label>
              <v-text-field v-model="filter" :placeholder="filterPlaceholder" hide-details />
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12">
              <div class="d-flex justify-end">
                <v-btn variant="elevated" class="me-4" @click="emit('submit')">Submit</v-btn>
                <v-btn variant="outlined" @click="emit('reset')">Reset</v-btn>
              </div>
            </v-col>
          </v-row>
        </v-card-item>
      </v-card>

      <v-card class="mt-4">
        <v-card-item>
          <v-row>
            <v-col class="d-flex justify-end mb-4" cols="12">
              <v-btn variant="elevated" color="primary" @click="emit('add')">{{ addLabel }}</v-btn>
            </v-col>
          </v-row>
          <slot />
        </v-card-item>
      </v-card>

      <slot name="dialog" />
    </v-col>
  </v-row>
</template>
