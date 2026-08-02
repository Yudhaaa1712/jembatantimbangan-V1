/**
 * Cache Manager
 * Replaces: includes/cache_manager.php
 * Uses node-cache for in-memory caching
 */
const NodeCache = require('node-cache');

const cache = new NodeCache({
  stdTTL: 3600,       // Default 1 hour
  checkperiod: 600,   // Check expired every 10 min
  useClones: false
});

function cacheGet(key) {
  return cache.get(key);
}

function cacheSet(key, value, ttl = 3600) {
  cache.set(key, value, ttl);
}

function cacheDelete(key) {
  cache.del(key);
}

function cacheHas(key) {
  return cache.has(key);
}

function cacheFlush() {
  cache.flushAll();
}

module.exports = { cacheGet, cacheSet, cacheDelete, cacheHas, cacheFlush };
