export function formatUSD(value) {
  if (value === null || value === undefined) return "0.00";

  return Number(value).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

export function formatCrypto(value, decimals = 6) {
  if (value === null || value === undefined) return "0";

  return Number(value).toFixed(decimals);
}
