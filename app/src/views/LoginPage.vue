<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Préstamos Móvil</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <form @submit.prevent="ingresar" novalidate>
        <ion-list lines="full">
          <ion-item>
            <ion-input v-model="email" label="Correo" label-placement="stacked" type="email"
              inputmode="email" autocomplete="username" required />
          </ion-item>
          <small v-if="errores.email" class="error-campo">{{ errores.email }}</small>
          <ion-item>
            <ion-input v-model="password" label="Contraseña" label-placement="stacked" type="password"
              autocomplete="current-password" required>
              <ion-input-password-toggle slot="end" />
            </ion-input>
          </ion-item>
          <small v-if="errores.password" class="error-campo">{{ errores.password }}</small>
        </ion-list>

        <ion-button type="submit" expand="block" class="ion-margin-top" :disabled="enviando">
          <ion-spinner v-if="enviando" name="crescent" />
          <span v-else>Ingresar</span>
        </ion-button>
      </form>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
// YA IMPLEMENTADO — depende de useAuth().login [M2]
import { ref } from 'vue';
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonList, IonItem, IonInput,
  IonInputPasswordToggle, IonButton, IonSpinner, useIonRouter,
} from '@ionic/vue';
import { useAuth } from '@/composables/useAuth';
import { ErrorApi } from '@/services/errores';
import { mostrarToast } from '@/services/avisos';

const { login } = useAuth();
const ionRouter = useIonRouter();

const email = ref('docente@ucr.ac.cr');
const password = ref('');
const errores = ref<Record<string, string>>({});
const enviando = ref(false);

async function ingresar() {
  errores.value = {};
  enviando.value = true;
  try {
    await login(email.value, password.value);
    ionRouter.navigate('/tabs/equipos', 'root', 'replace');
  } catch (error) {
    if (error instanceof ErrorApi && error.status === 422) errores.value = error.erroresPorCampo;
    else if (error instanceof ErrorApi && error.status === 429) mostrarToast('Demasiados intentos. Espere un minuto.', 'warning');
    else mostrarToast('No fue posible conectar con la API. ¿Está en ejecución?', 'danger');
  } finally {
    enviando.value = false;
  }
}
</script>
