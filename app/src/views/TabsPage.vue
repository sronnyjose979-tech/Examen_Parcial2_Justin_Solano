<template>
  <ion-page>
    <ion-tabs>
      <ion-router-outlet />
      <ion-tab-bar slot="bottom">
        <ion-tab-button tab="equipos" href="/tabs/equipos">
          <ion-icon :icon="laptopOutline" aria-hidden="true" />
          <ion-label>Equipos</ion-label>
        </ion-tab-button>
        <ion-tab-button tab="perfil" href="/tabs/perfil">
          <ion-icon :icon="personCircleOutline" aria-hidden="true" />
          <ion-label>Perfil</ion-label>
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>
  </ion-page>
</template>

<script setup lang="ts">
// YA IMPLEMENTADO
import { IonPage, IonTabs, IonRouterOutlet, IonTabBar, IonTabButton, IonIcon, IonLabel } from '@ionic/vue';
import { laptopOutline, personCircleOutline } from 'ionicons/icons';
</script>
