<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { api, setToken } from "../lib/api";
import { makeEcho } from "../lib/echo";
import { notify, toast } from "../lib/toast";
import { formatUSD, formatCrypto } from "../lib/format";

const me = reactive({ id: null });
const symbol = ref("BTC");

const form = reactive({
  symbol: "BTC",
  side: "buy",
  price: "",
  amount: "",
});

const state = reactive({
  wallet: { usd_balance: "0", assets: [] },
  orderbook: {
    buy: { data: [], current_page: 1, last_page: 1 },
    sell:{ data: [], current_page: 1, last_page: 1 },
  },
  myOrders: [],
  loading: false,
});

const pages = reactive({ buy_page: 1, sell_page: 1, per_page: 10 });

const filters = reactive({
  status: "all",
  side: "all",
});

const filteredMyOrders = computed(() => {
  return state.myOrders.filter(o => {
    if (filters.status !== "all" && o.status !== filters.status) return false;
    if (filters.side !== "all" && o.side !== filters.side) return false;
    return true;
  });
});

function assetRow(sym) {
  return state.wallet.assets?.find(a => a.symbol === sym);
}

async function loadProfile() {
  const { data } = await api.get("/api/profile");

  me.id = data.user.id;

  state.wallet = {
    usd_balance: data.user.balance,
    assets: data.assets,
  };
}

async function loadOrderbook() {
  const { data } = await api.get(
    `/api/orders?symbol=${encodeURIComponent(symbol.value)}&per_page=${pages.per_page}` +
    `&buy_page=${pages.buy_page}&sell_page=${pages.sell_page}`
  );

  state.orderbook.buy = data.buy;
  state.orderbook.sell = data.sell;
}

async function loadMyOrders() {
  const { data } = await api.get(`/api/my-orders?symbol=${encodeURIComponent(symbol.value)}`);
  state.myOrders = data.orders ?? data ?? [];
}

async function refreshAll() {
  state.loading = true;
  try {
    await Promise.all([loadProfile(), loadOrderbook(), loadMyOrders()]);
  } finally {
    state.loading = false;
  }
}

async function placeOrder() {
  try {
    if (!form.price || !form.amount) return notify("Price and Amount are required", "error");

    await api.post("/api/orders", {
      symbol: form.symbol,
      side: form.side,
      price: form.price,
      amount: form.amount,
    });

    notify("Order placed", "success");

    symbol.value = form.symbol;

    form.price = "";
    form.amount = "";

    await refreshAll();
  } catch (e) {
    notify(e?.response?.data?.message ?? "Failed to place order", "error");
  }
}

async function cancelOrder(id) {
  try {
    await api.post(`/api/orders/${id}/cancel`);
    notify("Order cancelled", "success");
    await refreshAll();
  } catch (e) {
    notify(e?.response?.data?.message ?? "Failed to cancel order", "error");
  }
}

function switchSymbol(sym) {
  symbol.value = sym;
  form.symbol = sym;
  pages.buy_page = 1;
  pages.sell_page = 1;
  refreshAll();
}

function logout() {
  localStorage.removeItem("token");
  setToken(null);
  location.href = "/login";
}

onMounted(async () => {
  const token = localStorage.getItem("token");
  if (!token) return (location.href = "/login");
  setToken(token);

  await refreshAll();

  const echo = makeEcho(token);

  if (me.id) {
    echo.private(`user.${me.id}`).listen(".OrderMatched", async () => {
      notify("Order matched!", "success");
      await refreshAll();
    });
  }
});
</script>

<template>
  <div class="min-h-screen relative overflow-hidden">
    <!-- Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950"></div>
    <div class="absolute -top-24 -left-24 w-96 h-96 bg-indigo-500/20 blur-3xl rounded-full"></div>
    <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-fuchsia-500/15 blur-3xl rounded-full"></div>

    <!-- Toast -->
    <div
      v-if="toast.show"
      class="fixed top-4 right-4 z-50 rounded-2xl shadow-xl px-4 py-3 text-sm border backdrop-blur bg-white/90"
      :class="toast.type === 'success'
        ? 'border-emerald-300'
        : toast.type === 'error'
          ? 'border-red-300'
          : 'border-slate-200'"
    >
      <div
        :class="toast.type === 'success'
          ? 'text-emerald-700'
          : toast.type === 'error'
            ? 'text-red-700'
            : 'text-slate-800'"
      >
        {{ toast.message }}
      </div>
    </div>

    <div class="relative p-6 space-y-6 max-w-7xl mx-auto">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <div class="w-10 h-10 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center text-white">
              ₿
            </div>
            <div>
              <div class="text-2xl font-semibold text-white">Mini Exchange</div>
              <div class="text-sm text-white/70">
                Trading Pair: <span class="font-semibold text-white">{{ symbol }}</span>
                <span v-if="state.loading" class="ml-2 inline-flex items-center gap-2 text-xs text-white/70">
                  <span class="w-3 h-3 rounded-full border-2 border-white/50 border-t-transparent animate-spin"></span>
                  Refreshing
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            class="px-4 py-2 rounded-2xl border backdrop-blur transition text-sm font-semibold"
            :class="symbol==='BTC'
              ? 'bg-white text-slate-900 border-white'
              : 'bg-white/10 text-white border-white/15 hover:bg-white/15'"
            @click="switchSymbol('BTC')"
          >
            BTC
          </button>

          <button
            class="px-4 py-2 rounded-2xl border backdrop-blur transition text-sm font-semibold"
            :class="symbol==='ETH'
              ? 'bg-white text-slate-900 border-white'
              : 'bg-white/10 text-white border-white/15 hover:bg-white/15'"
            @click="switchSymbol('ETH')"
          >
            ETH
          </button>

          <button
            class="px-4 py-2 rounded-2xl border backdrop-blur transition text-sm font-semibold
                   bg-white/10 text-white border-white/15 hover:bg-white/15"
            @click="logout"
          >
            Logout
          </button>
        </div>
      </div>

      <!-- Wallet / Overview -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-3xl border border-white/15 bg-white/10 backdrop-blur shadow-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <div class="text-white font-semibold">Wallet Overview</div>
            <div class="text-xs text-white/70">Live balances</div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
              <div class="text-xs text-white/70">USD Balance</div>
              <div class="mt-1 text-2xl font-bold text-white">{{ formatUSD(state.wallet.usd_balance) }}</div>
              <div class="mt-2 text-xs text-white/60">Available cash to trade</div>
            </div>

            <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
              <div class="text-xs text-white/70">BTC</div>
              <div class="mt-2 text-sm text-white/80">
                Avail: <b class="text-white">{{ formatCrypto(assetRow('BTC')?.amount ?? 0, 6) }}</b>
              </div>
              <div class="text-sm text-white/80">
                Locked: <b class="text-white">{{ formatCrypto(assetRow('BTC')?.locked_amount ?? 0, 6) }}</b>
              </div>
            </div>

            <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
              <div class="text-xs text-white/70">ETH</div>
              <div class="mt-2 text-sm text-white/80">
                Avail: <b class="text-white">{{ formatCrypto(assetRow('ETH')?.amount ?? 0, 6) }}</b>
              </div>
              <div class="text-sm text-white/80">
                Locked: <b class="text-white">{{ formatCrypto(assetRow('ETH')?.locked_amount ?? 0, 6) }}</b>
              </div>
            </div>
          </div>
        </div>

        <!-- Order Form -->
        <div class="rounded-3xl border border-white/15 bg-white/10 backdrop-blur shadow-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <div class="text-white font-semibold">Limit Order</div>
            <div class="text-xs text-white/70">Place a new order</div>
          </div>

          <form class="space-y-3" @submit.prevent="placeOrder">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-white/70 mb-1">Symbol</label>
                <select
                  class="w-full rounded-2xl px-3 py-2 border bg-white/10 text-white border-white/15 outline-none
                         focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-300"
                  v-model="form.symbol"
                >
                  <option class="text-slate-900">BTC</option>
                  <option class="text-slate-900">ETH</option>
                </select>
              </div>

              <div>
                <label class="block text-xs text-white/70 mb-1">Side</label>
                <select
                  class="w-full rounded-2xl px-3 py-2 border bg-white/10 text-white border-white/15 outline-none
                         focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-300"
                  v-model="form.side"
                >
                  <option class="text-slate-900" value="buy">Buy</option>
                  <option class="text-slate-900" value="sell">Sell</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-white/70 mb-1">Price (USD)</label>
                <input
                  class="w-full rounded-2xl px-3 py-2 border bg-white/10 text-white border-white/15 outline-none
                         focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-300 placeholder:text-white/40"
                  v-model="form.price"
                  placeholder="e.g. 42000"
                  inputmode="decimal"
                />
              </div>

              <div>
                <label class="block text-xs text-white/70 mb-1">Amount</label>
                <input
                  class="w-full rounded-2xl px-3 py-2 border bg-white/10 text-white border-white/15 outline-none
                         focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-300 placeholder:text-white/40"
                  v-model="form.amount"
                  placeholder="e.g. 0.05"
                  inputmode="decimal"
                />
              </div>
            </div>

            <button
              type="submit"
              class="w-full rounded-2xl px-4 py-2.5 font-semibold text-white shadow-lg transition active:scale-[0.99]
                     bg-gradient-to-r from-indigo-500 to-fuchsia-500 hover:from-indigo-600 hover:to-fuchsia-600
                     disabled:opacity-60 disabled:cursor-not-allowed"
              :disabled="state.loading"
            >
              Place Order
            </button>
          </form>
        </div>
      </div>

      <!-- Books + Orders -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Orderbook -->
        <div class="rounded-3xl border border-white/15 bg-white/10 backdrop-blur shadow-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <div class="text-white font-semibold">Orderbook — {{ symbol }}</div>
            <div class="text-xs text-white/70">Open orders</div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- BUY -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold text-emerald-200">BUY (high → low)</div>
              </div>

              <div class="space-y-2">
                <div
                  v-for="o in state.orderbook.buy.data"
                  :key="o.id"
                  class="rounded-2xl border border-white/10 bg-white/5 p-3 hover:bg-white/10 transition"
                >
                  <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-white">
                      {{ formatUSD(o.price) }}
                    </div>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-200 border border-emerald-500/20">
                      BUY
                    </span>
                  </div>
                  <div class="mt-1 text-xs text-white/70">
                    Amount:
                    <b class="text-white">{{ formatCrypto(o.remaining_amount ?? o.amount, 6) }}</b>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between mt-3">
                <button
                  class="px-3 py-1.5 rounded-2xl text-xs font-semibold border border-white/15 bg-white/10 text-white hover:bg-white/15 disabled:opacity-50"
                  :disabled="state.orderbook.buy.current_page <= 1"
                  @click="pages.buy_page--; loadOrderbook()"
                >
                  Prev
                </button>

                <div class="text-[11px] text-white/60">
                  Page {{ state.orderbook.buy.current_page }} / {{ state.orderbook.buy.last_page }}
                </div>

                <button
                  class="px-3 py-1.5 rounded-2xl text-xs font-semibold border border-white/15 bg-white/10 text-white hover:bg-white/15 disabled:opacity-50"
                  :disabled="state.orderbook.buy.current_page >= state.orderbook.buy.last_page"
                  @click="pages.buy_page++; loadOrderbook()"
                >
                  Next
                </button>
              </div>
            </div>

            <!-- SELL -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-semibold text-rose-200">SELL (low → high)</div>
              </div>

              <div class="space-y-2">
                <div
                  v-for="o in state.orderbook.sell.data"
                  :key="o.id"
                  class="rounded-2xl border border-white/10 bg-white/5 p-3 hover:bg-white/10 transition"
                >
                  <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-white">
                      {{ formatUSD(o.price) }}
                    </div>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-200 border border-rose-500/20">
                      SELL
                    </span>
                  </div>
                  <div class="mt-1 text-xs text-white/70">
                    Amount:
                    <b class="text-white">{{ formatCrypto(o.remaining_amount ?? o.amount, 6) }}</b>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between mt-3">
                <button
                  class="px-3 py-1.5 rounded-2xl text-xs font-semibold border border-white/15 bg-white/10 text-white hover:bg-white/15 disabled:opacity-50"
                  :disabled="state.orderbook.sell.current_page <= 1"
                  @click="pages.sell_page--; loadOrderbook()"
                >
                  Prev
                </button>

                <div class="text-[11px] text-white/60">
                  Page {{ state.orderbook.sell.current_page }} / {{ state.orderbook.sell.last_page }}
                </div>

                <button
                  class="px-3 py-1.5 rounded-2xl text-xs font-semibold border border-white/15 bg-white/10 text-white hover:bg-white/15 disabled:opacity-50"
                  :disabled="state.orderbook.sell.current_page >= state.orderbook.sell.last_page"
                  @click="pages.sell_page++; loadOrderbook()"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- My Orders -->
        <div class="rounded-3xl border border-white/15 bg-white/10 backdrop-blur shadow-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <div class="text-white font-semibold">My Orders</div>
            <div class="text-xs text-white/70">Open • Filled • Cancelled</div>
          </div>

          <div class="flex flex-wrap gap-2 mb-4">
            <select class="rounded-2xl px-3 py-2 text-sm border bg-white/10 text-white border-white/15 outline-none"
                    v-model="filters.status">
              <option class="text-slate-900" value="all">All status</option>
              <option class="text-slate-900" value="open">Open</option>
              <option class="text-slate-900" value="filled">Filled</option>
              <option class="text-slate-900" value="cancelled">Cancelled</option>
            </select>

            <select class="rounded-2xl px-3 py-2 text-sm border bg-white/10 text-white border-white/15 outline-none"
                    v-model="filters.side">
              <option class="text-slate-900" value="all">All side</option>
              <option class="text-slate-900" value="buy">Buy</option>
              <option class="text-slate-900" value="sell">Sell</option>
            </select>
          </div>

          <div v-if="filteredMyOrders.length === 0" class="text-sm text-white/70">
            No orders found.
          </div>

          <div class="space-y-2" v-else>
            <div
              v-for="o in filteredMyOrders"
              :key="o.id"
              class="rounded-2xl border border-white/10 bg-white/5 p-3 hover:bg-white/10 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span
                      class="text-[11px] px-2 py-0.5 rounded-full border"
                      :class="o.side === 'buy'
                        ? 'bg-emerald-500/15 text-emerald-200 border-emerald-500/20'
                        : 'bg-rose-500/15 text-rose-200 border-rose-500/20'"
                    >
                      {{ o.side.toUpperCase() }}
                    </span>

                    <span
                      class="text-[11px] px-2 py-0.5 rounded-full border"
                      :class="o.status === 'open'
                        ? 'bg-sky-500/15 text-sky-200 border-sky-500/20'
                        : o.status === 'filled'
                          ? 'bg-emerald-500/15 text-emerald-200 border-emerald-500/20'
                          : 'bg-slate-500/15 text-slate-200 border-slate-500/20'"
                    >
                      {{ o.status }}
                    </span>

                    <div class="text-sm text-white">
                      <b>{{ formatCrypto(o.amount, 6) }}</b>
                      <span class="text-white/70"> @ </span>
                      <b>{{ formatUSD(o.price) }}</b>
                    </div>
                  </div>

                  <div class="mt-1 text-xs text-white/60">
                    Remaining:
                    <b class="text-white">{{ formatCrypto(o.remaining_amount ?? o.amount, 6) }}</b>
                  </div>
                </div>

                <button
                  v-if="o.status === 'open'"
                  class="shrink-0 px-3 py-1.5 rounded-2xl text-xs font-semibold
                         border border-white/15 bg-white/10 text-white hover:bg-white/15"
                  @click="cancelOrder(o.id)"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="text-center text-xs text-white/40 pt-2">
        © {{ new Date().getFullYear() }} Mini Exchange
      </div>
    </div>
  </div>
</template>

