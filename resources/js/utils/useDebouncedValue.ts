import { useEffect, useState } from 'react';

/**
 * Returns a value that only updates after `delay` ms of no changes.
 * Useful for search inputs where firing on every keystroke is wasteful.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);

  return debounced;
}
