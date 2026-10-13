
import { useEffect, useState } from "react";

const START_DATE = "20261013";
const END_DATE = "20261031";

export function isOctobreRoseActive(
  now: Date = new Date(),
): boolean {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Europe/Paris",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(now);

  const values = Object.fromEntries(
    parts.map((part) => [part.type, part.value]),
  );

  const today =
    `${values.year}${values.month}${values.day}`;

  return today >= START_DATE && today <= END_DATE;
}

export function applyOctoberRoseDiscount(
  price: number,
): number {
  return Math.round(price * 0.8);
}

export function useOctobreRoseActive(): boolean {
  const [active, setActive] = useState(
    () => isOctobreRoseActive(),
  );

  useEffect(() => {
    const update = () => {
      setActive(isOctobreRoseActive());
    };

    update();

    const interval = window.setInterval(update, 60_000);

    return () => window.clearInterval(interval);
  }, []);

  return active;
}
