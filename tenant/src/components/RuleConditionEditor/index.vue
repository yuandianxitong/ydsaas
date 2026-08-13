<template>
  <div class="cond-block">
    <div class="cond-logic-bar">
      {{ t('component.ruleEditor.match') }}
      <el-radio-group :model-value="value.logic" size="small" @update:model-value="updateLogic">
        <el-radio-button value="AND">{{ t('component.ruleEditor.all') }}</el-radio-button>
        <el-radio-button value="OR">{{ t('component.ruleEditor.any') }}</el-radio-button>
      </el-radio-group>
      <i18n-t keypath="component.ruleEditor.suffix" tag="span">
        <template #em><strong>{{ t('component.ruleEditor.suffixEm') }}</strong></template>
      </i18n-t>
    </div>

    <div v-for="(c, idx) in value.conditions" :key="idx" class="cond-row">
      <el-radio-group v-model="c.exclude" size="small" class="cond-mode" @change="emitChange">
        <el-radio-button :value="false">{{ t('component.ruleEditor.include') }}</el-radio-button>
        <el-radio-button :value="true">{{ t('component.ruleEditor.exclude') }}</el-radio-button>
      </el-radio-group>
      <el-select v-model="c.field" :placeholder="t('component.ruleEditor.fieldPlaceholder')" style="width: 160px" @change="emitChange">
        <el-option v-for="opt in fieldOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
      </el-select>
      <el-select v-model="c.op" :placeholder="t('component.ruleEditor.opPlaceholder')" style="width: 90px" @change="emitChange">
        <el-option v-for="op in operatorOptions" :key="op.value" :label="op.label" :value="op.value" />
      </el-select>
      <el-input v-model="c.value" :placeholder="t('component.ruleEditor.valuePlaceholder')" style="flex: 1" @change="emitChange" />
      <el-button type="danger" text circle @click="remove(idx)">
        <i class="i-svg:x" />
      </el-button>
    </div>

    <div class="cond-actions">
      <el-button size="small" @click="add">{{ t('component.ruleEditor.addCondition') }}</el-button>
    </div>

    <div class="cond-hint">
      {{ t('component.ruleEditor.timeHint') }}
      <code>7_days_ago</code> / <code>14_days_ago</code> / <code>30_days_ago</code> / <code>90_days_ago</code> / <code>today</code> / <code>yesterday</code>
    </div>
  </div>
</template>

<script setup lang="ts" name="RuleConditionEditor">
import { reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'

export interface RuleCondition {
  field: string
  op: string
  value: string
  exclude: boolean
}

export interface Rules {
  logic: 'AND' | 'OR'
  conditions: RuleCondition[]
}

export interface FieldOption {
  value: string
  label: string
}

interface Props {
  modelValue: Rules | null
  fieldOptions: FieldOption[]
}
interface Emits {
  (e: 'update:modelValue', v: Rules): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()
const { t } = useI18n()

const operatorOptions = [
  { label: '=',  value: '=' },
  { label: '!=', value: '!=' },
  { label: '>',  value: '>' },
  { label: '>=', value: '>=' },
  { label: '<',  value: '<' },
  { label: '<=', value: '<=' },
]

// 内部状态：从 modelValue 同步初始值；用户编辑直接改 conditions 数组、再 emit
const value = reactive<Rules>({
  logic: 'AND',
  conditions: [],
})

const syncFromProp = () => {
  const v = props.modelValue
  value.logic = v?.logic === 'OR' ? 'OR' : 'AND'
  value.conditions = Array.isArray(v?.conditions)
    ? v.conditions.map((c) => ({
        field: String(c.field || ''),
        op: String(c.op || '='),
        value: c.value == null ? '' : String(c.value),
        exclude: !!c.exclude,
      }))
    : []
}

watch(() => props.modelValue, syncFromProp, { immediate: true, deep: true })

const emitChange = () => {
  emit('update:modelValue', {
    logic: value.logic,
    conditions: value.conditions.map((c) => ({ ...c })),
  })
}

const updateLogic = (v: string | number | boolean | undefined) => {
  value.logic = v === 'OR' ? 'OR' : 'AND'
  emitChange()
}

const add = () => {
  value.conditions.push({ field: '', op: '=', value: '', exclude: false })
  emitChange()
}

const remove = (idx: number) => {
  value.conditions.splice(idx, 1)
  emitChange()
}
</script>

<style lang="scss" scoped>
/* 令牌映射（对齐 ProTable/index.vue 的 Shop ink-* → tenant 语义变量约定）：
 * --ink-50→--color-surface-sunken、--ink-100→--color-divider、
 * --ink-200→--color-border、--ink-500→--color-text-tertiary */
.cond-block {
  width: 100%;
  padding: 14px;
  background: var(--color-surface-sunken, #f6f7fa);
  border-radius: 8px;
  border: 1px solid var(--color-divider, #eef0f5);
}
.cond-logic-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 12.5px;
  color: var(--color-text-tertiary, #8b95a7);
}
.cond-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 0;
  & + .cond-row { border-top: 1px dashed var(--color-border, #dde0e6); }
}
.cond-mode { flex-shrink: 0; }
.cond-actions {
  margin-top: 12px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.cond-hint {
  margin-top: 10px;
  font-size: 12px;
  color: var(--color-text-tertiary, #8b95a7);
  code {
    background: var(--color-surface, #fff);
    padding: 1px 6px;
    border-radius: 3px;
    border: 1px solid var(--color-divider, #eef0f5);
    font-size: 11.5px;
  }
}
</style>
