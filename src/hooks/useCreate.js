import { useState, useEffect, useMemo, useCallback, useRef } from '@wordpress/element';
import ResourcesAPIService from '../ResourcesAPIService';

const useCreate = (type) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  
  const api = useMemo(() => new ResourcesAPIService(type), [type]);
  
  const create = useCallback(async (body) => {
    try {
      setLoading(true);
      setError(null);
      const result = await api.create(body);
      return result; // Return data instead of storing in state
    } catch (e) {
      setError(e?.message ?? String(e));
      throw e; // Re-throw so caller can handle
    } finally {
      setLoading(false);
    }
  }, [api]);
  
  return { create, loading, error };
};

export default useCreate;
