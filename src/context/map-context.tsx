
'use client';

import { createContext, useContext, useState, ReactNode, useMemo, useEffect } from 'react';

// Coordinates are stored as [latitude, longitude] to match Leaflet's convention
type MapCoordinates = [number, number][];

const DEFAULT_COORDS: MapCoordinates = [
  [18.4781, -77.9278],
  [18.4783, -77.9239],
  [18.4752, -77.9236],
  [18.4750, -77.9276],
];

type MapContextType = {
  coordinates: MapCoordinates;
  setCoordinates: (coords: MapCoordinates) => void;
};

const MapContext = createContext<MapContextType | undefined>(undefined);

export function MapProvider({ children }: { children: ReactNode }) {
  const [coordinates, setCoordinatesState] = useState<MapCoordinates>(DEFAULT_COORDS);

  useEffect(() => {
    const savedCoords = localStorage.getItem('map-coordinates');
    if (savedCoords) {
        setCoordinatesState(JSON.parse(savedCoords));
    }
  }, []);

  const setCoordinates = (newCoords: MapCoordinates) => {
    setCoordinatesState(newCoords);
    localStorage.setItem('map-coordinates', JSON.stringify(newCoords));
  }

  const value = useMemo(() => ({ coordinates, setCoordinates }), [coordinates]);

  return (
    <MapContext.Provider value={value}>
      {children}
    </MapContext.Provider>
  );
}

export function useMap() {
  const context = useContext(MapContext);
  if (context === undefined) {
    throw new Error('useMap must be used within a MapProvider');
  }
  return context;
}
