<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Perfil</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content>
      <ion-list>
        <ion-list-header>Sesión</ion-list-header>
        <ion-item>
          <ion-icon slot="start" :icon="personCircleOutline" aria-hidden="true" />
          <ion-label>{{ usuario?.nombre ?? '—' }}</ion-label>
        </ion-item>

        <ion-list-header>Dispositivo</ion-list-header>
        <ion-item><ion-label>Plataforma</ion-label><ion-note slot="end">{{ plataforma }}</ion-note></ion-item>
        <ion-item><ion-label>Modelo</ion-label><ion-note slot="end">{{ dispositivo?.model ?? '—' }}</ion-note></ion-item>
        <ion-item><ion-label>Sistema</ion-label><ion-note slot="end">{{ dispositivo ? `${dispositivo.operatingSystem} ${dispositivo.osVersion}` : '—' }}</ion-note></ion-item>
        <ion-item><ion-label>Conexión</ion-label><ion-badge slot="end" :color="conectado ? 'success' : 'warning'">{{ conectado ? tipo : 'sin conexión' }}</ion-badge></ion-item>
      </ion-list>

      <div class="ion-padding">
        <ion-button expand="block" color="danger" fill="outline" @click="salir">Cerrar sesión</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonList, IonListHeader, IonItem, IonIcon,
  IonLabel, IonNote, IonBadge, IonButton, onIonViewWillEnter, useIonRouter,
} from '@ionic/vue';
import { personCircleOutline } from 'ionicons/icons';
import { Capacitor } from '@capacitor/core';
import { Device, type DeviceInfo } from '@capacitor/device';
import { useAuth } from '@/composables/useAuth';
import { useConexion } from '@/composables/useConexion';

const { usuario, cerrarSesion } = useAuth();
const { conectado, tipo } = useConexion();
const ionRouter = useIonRouter();

const plataforma = Capacitor.getPlatform();
const dispositivo = ref<DeviceInfo | null>(null);
onIonViewWillEnter(async () => { dispositivo.value = await Device.getInfo(); });

async function salir() {
  await cerrarSesion();
  ionRouter.navigate('/login', 'root', 'replace');
}
</script>
