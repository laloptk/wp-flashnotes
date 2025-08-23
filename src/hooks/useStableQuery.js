import { useRef, useMemo } from '@wordpress/element';

function shallowEqual(a = {}, b = {}) {
  const keysA = Object.keys(a);
  const keysB = Object.keys(b);
  if (keysA.length !== keysB.length) return false;
  for (const key of keysA) {
    if (a[key] !== b[key]) return false;
  }
  return true;
}

function useStableQuery(queryParams) {
  const prevRef = useRef({});
  return useMemo(() => {
    if (!shallowEqual(prevRef.current, queryParams)) {
      prevRef.current = queryParams;
    }
    return prevRef.current;
  }, [queryParams]);
}

export default useStableQuery;