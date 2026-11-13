<script setup>
import { PrinterOutlined, GiftOutlined, WalletOutlined, FileTextOutlined, CheckCircleFilled, WarningFilled } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import StatusTag from '@/Components/StatusTag.vue';
import { money } from '@/lib/format';
import { openFile } from '@/lib/viewer';

defineProps({ student: Object, children: Array, isGuardian: Boolean, charges: Array, payments: Array, balance: Number, scholarships: Array });

// Mapeo de estado (estilo incidencias)
const STATUS_MAP = {
    pendiente: { icon: WarningFilled, color: '#f39200', bg: '#fff8eb', label: 'Pendiente' },
    parcial: { icon: WarningFilled, color: '#7c4ddb', bg: '#f3effa', label: 'Parcial' },
    pagado: { icon: CheckCircleFilled, color: '#2e9b43', bg: '#ecfdf3', label: 'Pagado' },
    cancelado: { icon: WarningFilled, color: '#e3262b', bg: '#fef2f2', label: 'Cancelado' }
};
const downloadReceipt = (p) => { window.location.href = p.receipt_url; }; // el recibo se descarga
</script>

<template>
    <AppLayout title="Pagos">
        <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Pagos y adeudos" />

        <!-- SALDO DESTACADO -->
        <div class="balance-card" :class="{ 'is-ok': balance <= 0 }">
            <div class="balance-icon">
                <WalletOutlined v-if="balance > 0" />
                <CheckCircleFilled v-else />
            </div>
            <div class="balance-info">
                <span>Saldo pendiente</span>
                <b>{{ money(balance) }}</b>
                <small>{{ balance > 0 ? 'Acude a caja o realiza tu transferencia con la referencia de tu institución.' : 'Estás al corriente. ¡Gracias!' }}</small>
            </div>
        </div>

        <!-- BECAS -->
        <div v-for="s in scholarships" :key="s.name" class="scholarship-card">
            <div class="sc-icon"><GiftOutlined /></div>
            <div class="sc-info">
                <b>{{ s.name }}</b>
                <span>{{ s.percentage ? s.percentage + '% de descuento' : money(s.amount) + ' de descuento' }}</span>
            </div>
        </div>

        <a-row :gutter="[20, 20]">
            <!-- ADEUDOS Y SERVICIOS -->
            <a-col :xs="24" :lg="14">
                <a-card :bordered="false" class="main-card">
                    <template #title><span class="sg-card-title" style="--c: #1d7fc9"><FileTextOutlined /> Adeudos y servicios</span></template>
                    <a-table :data-source="charges" row-key="id" size="middle" :pagination="{ pageSize: 10 }" :scroll="{ x: 520 }" :columns="[
                        { title: 'Concepto', dataIndex: 'concept' }, { title: 'Vence', dataIndex: 'due_on', width: 110 },
                        { title: 'Saldo', key: 'balance', width: 120, align: 'right' }, { title: 'Estado', key: 'status', width: 130 }]"
                        class="custom-table">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'balance'">
                                <b :style="{ color: record.balance > 0 ? '#dc2626' : '#16a34a' }">{{ money(record.balance) }}</b>
                                <div v-if="record.discount" class="sg-cell-sub">beca -{{ money(record.discount) }}</div>
                            </template>
                            <template v-else-if="column.key === 'status'">
                                <span class="status-pill" :style="{ background: STATUS_MAP[record.status]?.bg, color: STATUS_MAP[record.status]?.color }">
                                    <component :is="STATUS_MAP[record.status]?.icon" class="status-icon" />
                                    {{ STATUS_MAP[record.status]?.label }}
                                </span>
                                <span v-if="record.overdue" class="status-pill" style="background: #fef2f2; color: #e3262b; margin-left: 6px;">
                                    Vencido
                                </span>
                            </template>
                        </template>
                    </a-table>
                </a-card>
            </a-col>

            <!-- HISTORIAL DE PAGOS -->
            <a-col :xs="24" :lg="10">
                <a-card :bordered="false" class="main-card">
                    <template #title><span class="sg-card-title" style="--c: #2e9b43"><PrinterOutlined /> Historial de pagos</span></template>
                    <a-empty v-if="!payments.length" :image="false" description="Sin pagos registrados" />
                    <div v-for="p in payments" :key="p.id" class="payment-item">
                        <div class="pi-icon"><PrinterOutlined /></div>
                        <div class="pi-info">
                            <b class="pi-amount">{{ money(p.amount) }}</b>
                            <span class="pi-concept">{{ p.concept }}</span>
                            <span class="pi-meta">{{ p.paid_at }} · {{ p.method }}</span>
                        </div>
                        <a-button type="text" size="small" class="pi-btn" @click="downloadReceipt(p)">
                            <PrinterOutlined /> {{ p.folio }}
                        </a-button>
                    </div>
                </a-card>
            </a-col>
        </a-row>
    </AppLayout>
</template>

<style scoped>
/* ============ TARJETA DE SALDO ============ */
.balance-card {
    display: flex;
    align-items: center;
    gap: 20px;
    background: linear-gradient(135deg, #b91c1c, #dc2626);
    color: #fff;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 20px -8px rgba(185, 28, 28, 0.4);
}
.balance-card.is-ok {
    background: linear-gradient(135deg, #0f5a99, #14a39a);
    box-shadow: 0 8px 20px -8px rgba(15, 90, 153, 0.4);
}
.balance-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.2);
    display: grid;
    place-items: center;
    font-size: 28px;
    flex: none;
}
.balance-info {
    display: flex;
    flex-direction: column;
}
.balance-info span {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.85;
    font-weight: 600;
}
.balance-info b {
    font-size: 2.2rem;
    font-weight: 900;
    line-height: 1.1;
}
.balance-info small {
    font-size: 13px;
    opacity: 0.9;
    margin-top: 4px;
}

/* ============ BECAS ============ */
.scholarship-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 12px;
    color: #166534;
}
.sc-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #2e9b43;
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 18px;
    flex: none;
}
.sc-info b {
    font-size: 14px;
}
.sc-info span {
    font-size: 13px;
    display: block;
    margin-top: 2px;
}

/* ============ TARJETAS PRINCIPALES ============ */
.main-card {
    border-radius: 16px;
    box-shadow: 0 4px 12px -4px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
    height: 100%;
}

/* ============ TABLA DE ADEUDOS ============ */
:deep(.custom-table .ant-table-thead > tr > th) {
    background: #f8fafc !important;
    color: #475569 !important;
    font-weight: 700;
    font-size: 12.5px;
    border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
:deep(.custom-table .ant-table-tbody > tr > td) {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
}
:deep(.custom-table .ant-table-tbody > tr:hover > td) {
    background: #f8fafc;
}

/* ============ ESTADO (Píldora) ============ */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid color-mix(in srgb, currentColor 20%, transparent);
    white-space: nowrap;
}
.status-icon {
    font-size: 12px;
}

/* ============ HISTORIAL DE PAGOS ============ */
.payment-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 10px;
    transition: all 0.2s;
}
.payment-item:hover {
    background: #fff;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.pi-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #ecfdf5;
    color: #2e9b43;
    display: grid;
    place-items: center;
    font-size: 18px;
    flex: none;
}
.pi-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.pi-amount {
    font-size: 16px;
    color: #2e9b43;
    font-weight: 800;
}
.pi-concept {
    font-size: 13px;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pi-meta {
    font-size: 11.5px;
    color: #64748b;
}
.pi-btn {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-weight: 600;
    border-radius: 6px;
    font-size: 12px;
}
.pi-btn:hover {
    color: #1d7fc9;
    border-color: #1d7fc9;
}
</style>