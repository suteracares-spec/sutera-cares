import { Card, Typography } from "heroui-native";
import { useState } from "react";
import { View } from "react-native";

import { ChoiceList, Field, SubmitButton, useFieldErrors, useOutcome } from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { StatusChip } from "@/components/ui/Status";
import type { ConcernCategory, FamilyConcern } from "@/lib/api";
import { shortDay } from "@/lib/format";
import { useOfficeAction } from "@/lib/office";

/** Tell the office something, and see what happened to earlier messages. */
export function ConcernForm({
  path,
  categories,
  concerns,
  onSent,
}: {
  path: string;
  categories: ConcernCategory[];
  concerns: FamilyConcern[];
  onSent: () => void;
}) {
  const [category, setCategory] = useState<string | null>(null);
  const [detail, setDetail] = useState("");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const send = async () =>
    setErrors(
      handle(await run(path, { category, detail }), () => {
        setCategory(null);
        setDetail("");
        onSent();
      })
    );

  return (
    <>
      <Section title="What is it about?">
        <ChoiceList value={category} onChange={setCategory} options={categories} />
        {first("category") && (
          <Typography type="body-sm" className="text-danger">
            {first("category")}
          </Typography>
        )}
        <Field
          label="Tell us more"
          value={detail}
          onChange={setDetail}
          multiline
          required
          error={first("detail")}
          placeholder="What happened, and when"
        />
        <Typography type="body-xs" color="muted">
          This goes straight to the office. If someone is in danger, call 999 first.
        </Typography>
        <SubmitButton label="Send to the office" busy={busy} onPress={send} />
      </Section>

      {concerns.length > 0 && (
        <Section title="Your messages">
          <Card>
            <Card.Body className="gap-3">
              {concerns.map((c) => (
                <View key={c.id} className="flex-row items-center justify-between gap-2">
                  <Typography type="body-sm" className="flex-1">
                    {shortDay(c.date)}: {c.category}
                  </Typography>
                  <StatusChip status={c.open ? "investigating" : "resolved"} label={c.open ? "With the office" : "Resolved"} />
                </View>
              ))}
            </Card.Body>
          </Card>
        </Section>
      )}
    </>
  );
}
