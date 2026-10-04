import { createContext, useContext, useState, type ReactNode } from "react";

import type { FamilyClient } from "./api";
import { useOffice } from "./office";

type Linked = { id: number; name: string; relationship: string | null };

const FamilyContext = createContext<{
  clients: Linked[] | null;
  error: string | null;
  selected: number | null;
  select: (id: number) => void;
}>({ clients: null, error: null, selected: null, select: () => {} });

/**
 * The people this family account looks after, and which one is on screen.
 * Most families look after one person, so the first is chosen for them.
 */
export function FamilyProvider({ children }: { children: ReactNode }) {
  const { data, error } = useOffice<{ clients: Linked[] }>("/family/clients");
  const [chosen, setChosen] = useState<number | null>(null);
  const selected = chosen ?? data?.clients[0]?.id ?? null;

  return (
    <FamilyContext.Provider value={{ clients: data?.clients ?? null, error, selected, select: setChosen }}>
      {children}
    </FamilyContext.Provider>
  );
}

export function useFamily() {
  return useContext(FamilyContext);
}

/** The chosen person's overview: visits, care plan, invoices, messages. */
export function useFamilyClient() {
  const { selected } = useFamily();
  return useOffice<FamilyClient>(selected ? `/family/clients/${selected}` : null);
}
