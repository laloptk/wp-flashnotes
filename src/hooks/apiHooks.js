import { useMemo } from '@wordpress/element';
import ResourcesAPIService from '../ResourcesAPIService';

// Simple service cache - no context needed
const serviceCache = new Map();

/**
 * Get or create a cached service instance
 * @param {string} type - Resource type
 * @returns {ResourcesAPIService} - Service instance
 */
const getService = (type) => { 
  if (!serviceCache.has(type)) {
    serviceCache.set(type, new ResourcesAPIService(type));
  }
  
  return serviceCache.get(type);
};

/**
 * Hook to access the API service factory
 * @returns {object} - Service factory with getService method
 */
export const useAPIFactory = () => {
  return useMemo(() => ({
    getService,
    clearCache: (type = null) => {
      if (type) {
        serviceCache.delete(type);
      } else {
        serviceCache.clear();
      }
    },
    getCachedTypes: () => Array.from(serviceCache.keys())
  }), []);
};

/**
 * Hook to get a specific API service instance
 * @param {string} type - Resource type ('notes', 'cards')
 * @returns {ResourcesAPIService} - Service instance
 */
export const useAPIService = (type) => {
  const factory = useAPIFactory();
  
  return useMemo(
    () => factory.getService(type), 
    [factory, type]
  );
};