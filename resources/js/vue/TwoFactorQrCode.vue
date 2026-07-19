<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, required: true },
});

const canvas = ref(null);

async function renderQrCode() {
    await nextTick();
    if (!canvas.value || !props.value) return;

    try {
        await QRCode.toCanvas(canvas.value, props.value, {
            errorCorrectionLevel: 'M',
            margin: 2,
            width: 220,
            color: {
                dark: '#1f2a2b',
                light: '#fffdf8',
            },
        });
    } catch {
        canvas.value.getContext('2d')?.clearRect(0, 0, canvas.value.width, canvas.value.height);
    }
}

onMounted(renderQrCode);
watch(() => props.value, renderQrCode);
</script>

<template>
    <div class="two-factor-qr-shell">
        <canvas ref="canvas" class="two-factor-qr-canvas" role="img" :aria-label="label"></canvas>
    </div>
</template>
