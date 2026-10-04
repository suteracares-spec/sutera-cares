import { Alert, Button } from "heroui-native";

import { useVisits } from "@/lib/visits";

/** Says plainly whether what the caregiver did has reached the office yet. */
export function SyncBanner() {
  const { queue, problems, online, dismissProblem } = useVisits();

  return (
    <>
      {problems.map((p) => (
        <Alert key={p.id} status="danger">
          <Alert.Indicator />
          <Alert.Content>
            <Alert.Title>
              {p.kind === "check-in" ? "Check-in not accepted" : "Check-out not accepted"}
            </Alert.Title>
            <Alert.Description>{p.message} Call the office if you are unsure.</Alert.Description>
          </Alert.Content>
          <Button size="sm" variant="ghost" onPress={() => dismissProblem(p.id)}>
            OK
          </Button>
        </Alert>
      ))}

      {queue.length > 0 && (
        <Alert status="warning">
          <Alert.Indicator />
          <Alert.Content>
            <Alert.Title>
              {queue.length} {queue.length === 1 ? "action" : "actions"} waiting to send
            </Alert.Title>
            <Alert.Description>
              {online
                ? "Sending now."
                : "Saved on this phone with the time you tapped. It sends by itself when there is signal."}
            </Alert.Description>
          </Alert.Content>
        </Alert>
      )}

      {queue.length === 0 && !online && (
        <Alert status="default">
          <Alert.Indicator />
          <Alert.Content>
            <Alert.Title>No signal</Alert.Title>
            <Alert.Description>
              You are seeing the shifts saved on this phone. Check-in still works.
            </Alert.Description>
          </Alert.Content>
        </Alert>
      )}
    </>
  );
}
