import { useEffect, useState } from "react";

const START = "2026-11-18T00:00:00";
const END_EXCLUSIVE = "2026-12-01T00:00:00";

function parisLocalDateTime(now: Date = new Date()): string {
  const parts = new Intl.DateTimeFormat("sv-SE", {
    timeZone: "Europe/Paris",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hourCycle: "h23",
  }).formatToParts(now);
  const values = Object.fromEntries(parts.map((p) => [p.type, p.value]));
  return `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}:${values.second}`;
}

export function isBlackFridayActive(now: Date = new Date()): boolean {
  const local = parisLocalDateTime(now);
  return local >= START && local < END_EXCLUSIVE;
}

export function useBlackFridayActive(): boolean {
  const [active, setActive] = useState(() => isBlackFridayActive());
  useEffect(() => {
    const update = () => setActive(isBlackFridayActive());
    update();
    const timer = window.setInterval(update, 30_000);
    return () => window.clearInterval(timer);
  }, []);
  return active;
}

export function blackFridayPrice(price: number): number {
  return Math.round(price * 0.6);
}
