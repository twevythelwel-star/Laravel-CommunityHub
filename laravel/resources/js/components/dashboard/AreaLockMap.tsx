/**
 * AreaLockMap — Community boundary map with geofenced pan/zoom constraints.
 * This component wraps LeafletMapFixed and is used on the /dashboard/map route
 * to display the community boundary polygon drawn from MapContext coordinates.
 *
 * The actual map initialisation and boundary rendering logic lives in:
 *   - LeafletMapFixed.tsx  (Leaflet instance lifecycle)
 *   - /dashboard/map/page.tsx  (GeoJSON layer + bounds enforcement)
 */
export { default } from './LeafletMapFixed';
