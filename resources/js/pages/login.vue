<script setup>
import { ref, computed } from "vue";
import { api, setToken } from "../lib/api";
import { notify, toast } from "../lib/toast";

const email = ref("");
const password = ref("");
const showPassword = ref(false);
const loading = ref(false);
const touched = ref({ email: false, password: false });

const emailError = computed(() => {
  if (!touched.value.email) return "";
  if (!email.value.trim()) return "Email is required";
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
  return ok ? "" : "Enter a valid email address";
});

const passwordError = computed(() => {
  if (!touched.value.password) return "";
  if (!password.value.trim()) return "Password is required";
  if (password.value.trim().length < 6) return "Password must be at least 6 characters";
  return "";
});

const isValid = computed(() => !emailError.value && !passwordError.value);

async function login() {
  touched.value.email = true;
  touched.value.password = true;

  if (!isValid.value) {
    notify("Please fix the errors and try again", "error");
    return;
  }

  if (loading.value) return;

  loading.value = true;
  try {
    const { data } = await api.post("/api/auth/login", {
      email: email.value.trim(),
      password: password.value,
    });

    localStorage.setItem("token", data.token);
    setToken(data.token);
    location.href = "/";
  } catch (e) {
    notify("Invalid credentials", "error");
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600"></div>
    <div class="absolute -top-24 -left-24 w-96 h-96 bg-white/15 blur-3xl rounded-full"></div>
    <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-white/10 blur-3xl rounded-full"></div>

    <div class="relative min-h-screen flex items-center justify-center p-6">
      <div class="w-full max-w-md">
        <div class="text-center mb-6">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 text-white text-sm">
            <span class="w-2 h-2 rounded-full bg-emerald-300"></span>
            Mini Exchange Engine
          </div>
          <h1 class="mt-3 text-3xl font-bold text-white">Welcome back</h1>
          <p class="mt-1 text-white/80 text-sm">Sign in to continue</p>
        </div>
        <div class="bg-white/90 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/40 p-6 sm:p-8">
          <div v-if="toast.show" class="mb-4 rounded-2xl px-4 py-3 text-sm"
               :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-slate-50 text-slate-700 border border-slate-100'">
            {{ toast.message }}
          </div>

          <form class="space-y-4" @submit.prevent="login" novalidate>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
              <div
                class="flex items-center gap-2 rounded-2xl border bg-white px-3 py-2 shadow-sm focus-within:ring-4"
                :class="emailError
                  ? 'border-red-300 focus-within:ring-red-100'
                  : 'border-slate-200 focus-within:border-indigo-400 focus-within:ring-indigo-100'"
              >
                <span class="text-slate-400">✉️</span>
                <input
                  v-model.trim="email"
                  type="email"
                  required
                  autocomplete="username"
                  placeholder="testuser1@test.com"
                  class="w-full outline-none bg-transparent text-slate-800 placeholder:text-slate-400"
                  @blur="touched.email = true"
                />
              </div>
              <p v-if="emailError" class="mt-1 text-xs text-red-600">{{ emailError }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>

              <div
                class="flex items-center gap-2 rounded-2xl border bg-white px-3 py-2 shadow-sm focus-within:ring-4"
                :class="passwordError
                  ? 'border-red-300 focus-within:ring-red-100'
                  : 'border-slate-200 focus-within:border-indigo-400 focus-within:ring-indigo-100'"
              >
                <span class="text-slate-400">🔒</span>

                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  minlength="6"
                  autocomplete="current-password"
                  placeholder="••••••"
                  class="w-full outline-none bg-transparent text-slate-800 placeholder:text-slate-400"
                  @blur="touched.password = true"
                />

                <button
                  type="button"
                  class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 px-2 py-1 rounded-xl hover:bg-indigo-50"
                  @click="showPassword = !showPassword"
                >
                  {{ showPassword ? "HIDE" : "SHOW" }}
                </button>
              </div>

              <p v-if="passwordError" class="mt-1 text-xs text-red-600">{{ passwordError }}</p>
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full rounded-2xl px-4 py-2.5 font-semibold text-white shadow-lg transition active:scale-[0.99]
                     bg-gradient-to-r from-indigo-600 to-fuchsia-600 hover:from-indigo-700 hover:to-fuchsia-700
                     disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="!loading">Login</span>
              <span v-else class="inline-flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-white/70 border-t-transparent animate-spin"></span>
                Logging in...
              </span>
            </button>

            <div class="text-center text-xs text-slate-500">
              Use: <span class="font-mono bg-slate-100 px-2 py-0.5 rounded-lg">testuser1@test.com</span>
              &nbsp; / &nbsp;
              <span class="font-mono bg-slate-100 px-2 py-0.5 rounded-lg">123456</span>
            </div>
          </form>
        </div>

        <p class="text-center mt-5 text-xs text-white/70">
          © {{ new Date().getFullYear() }} Mini Exchange Engine
        </p>
      </div>
    </div>
  </div>
</template>
