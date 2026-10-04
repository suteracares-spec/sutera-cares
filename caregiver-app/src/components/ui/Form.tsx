import { Check, ChevronLeft, ChevronRight } from "lucide-react-native";
import {
  Button,
  Card,
  ControlField,
  Description,
  FieldError,
  Input,
  Label,
  Spinner,
  Switch,
  TextArea,
  TextField,
  Typography,
  useThemeColor,
  useToast,
} from "heroui-native";
import { useState, type ReactNode } from "react";
import { Pressable, View } from "react-native";

import { addDays, malaysiaDate, shortDay } from "@/lib/format";
import type { ActionOutcome } from "@/lib/office";

/** A titled card holding one action's form, with a close link. */
export function ActionCard({
  title,
  onClose,
  children,
}: {
  title: string;
  onClose: () => void;
  children: ReactNode;
}) {
  return (
    <Card>
      <Card.Header className="flex-row items-center justify-between">
        <Card.Title>{title}</Card.Title>
        <Button size="sm" variant="ghost" onPress={onClose}>
          Close
        </Button>
      </Card.Header>
      <Card.Body className="gap-3">{children}</Card.Body>
    </Card>
  );
}

/** The submit button every action form uses: shows progress while busy. */
export function SubmitButton({
  label,
  busy,
  onPress,
  danger,
}: {
  label: string;
  busy: boolean;
  onPress: () => void;
  danger?: boolean;
}) {
  const fg = useThemeColor(danger ? "danger-foreground" : "accent-foreground");
  return (
    <Button variant={danger ? "danger" : "primary"} onPress={onPress} isDisabled={busy}>
      {busy ? <Spinner size="sm" color={fg} /> : label}
    </Button>
  );
}

/**
 * Turns an action's outcome into what the person sees: a green toast and
 * the follow-up on success; on refusal, the field's own error, else a red
 * toast. Returns the field errors for the form to show inline.
 */
export function useOutcome() {
  const { toast } = useToast();
  return function handle<T>(
    outcome: ActionOutcome<T>,
    onSuccess: (data: T & { message?: string }) => void
  ): Record<string, string[]> {
    if (outcome.ok) {
      toast.show({ variant: "success", label: outcome.data.message ?? "Saved." });
      onSuccess(outcome.data);
      return {};
    }
    if (Object.keys(outcome.fields).length === 0) {
      toast.show({ variant: "danger", label: outcome.message });
    }
    return outcome.fields;
  };
}

/** A required reason, e.g. why a shift is cancelled. */
export function ReasonField({
  label,
  value,
  onChange,
  placeholder,
  error,
  long,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  error?: string;
  long?: boolean;
}) {
  return (
    <TextField isRequired isInvalid={!!error}>
      <Label>{label}</Label>
      {long ? (
        <TextArea value={value} onChangeText={onChange} placeholder={placeholder} />
      ) : (
        <Input
          variant="secondary"
          value={value}
          onChangeText={onChange}
          placeholder={placeholder}
        />
      )}
      <FieldError>{error}</FieldError>
    </TextField>
  );
}

/** "08:00" in, 24-hour, checked as typed. */
export function TimeField({
  label,
  value,
  onChange,
  error,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  error?: string;
}) {
  const valid = /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
  return (
    <TextField isRequired isInvalid={!!error || (value.length >= 4 && !valid)} className="flex-1">
      <Label>{label}</Label>
      <Input
        variant="secondary"
        value={value}
        onChangeText={(v) => {
          // Type "0830" and get "08:30".
          const digits = v.replace(/\D/g, "").slice(0, 4);
          onChange(digits.length > 2 ? `${digits.slice(0, 2)}:${digits.slice(2)}` : digits);
        }}
        keyboardType="number-pad"
        placeholder="HH:MM"
        maxLength={5}
      />
      <FieldError>
        {error ?? (value.length >= 4 && !valid ? "Use 24-hour time, e.g. 08:30" : "")}
      </FieldError>
    </TextField>
  );
}

/** Pick a day by stepping back and forward. */
export function DayStepper({ value, onChange }: { value: string; onChange: (d: string) => void }) {
  const accent = useThemeColor("accent");
  return (
    <View className="gap-1">
      <Label>Day</Label>
      <View className="flex-row items-center gap-3">
        <Button
          size="sm"
          variant="secondary"
          isIconOnly
          onPress={() => onChange(addDays(value, -1))}
        >
          <ChevronLeft size={18} color={accent} />
        </Button>
        <Typography weight="semibold" className="flex-1 text-center">
          {shortDay(value)}
        </Typography>
        <Button
          size="sm"
          variant="secondary"
          isIconOnly
          onPress={() => onChange(addDays(value, 1))}
        >
          <ChevronRight size={18} color={accent} />
        </Button>
      </View>
    </View>
  );
}

/** Choose one from a short list: a person, a status. */
export function ChoiceList<V extends string | number | null>({
  options,
  value,
  onChange,
}: {
  options: { value: V; label: string; hint?: string | null }[];
  value: V;
  onChange: (v: V) => void;
}) {
  const accent = useThemeColor("accent");
  return (
    <View className="rounded-2xl overflow-hidden border border-separator">
      {options.map((o, i) => {
        const selected = o.value === value;
        return (
          <Pressable
            key={String(o.value)}
            onPress={() => onChange(o.value)}
            accessibilityRole="radio"
            accessibilityState={{ selected }}
            className={`flex-row items-center gap-3 px-4 py-3 ${i < options.length - 1 ? "border-b border-separator" : ""} ${selected ? "bg-accent-soft" : ""}`}
          >
            <View className="flex-1">
              <Typography weight={selected ? "semibold" : "normal"}>{o.label}</Typography>
              {o.hint ? (
                <Typography type="body-sm" color="muted">
                  {o.hint}
                </Typography>
              ) : null}
            </View>
            {selected ? <Check size={20} color={accent} /> : null}
          </Pressable>
        );
      })}
    </View>
  );
}

/** The local state every action form needs: its fields' errors. */
export function useFieldErrors() {
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const first = (field: string) => errors[field]?.[0];
  return { errors, setErrors, first };
}

/** A labelled text box; `multiline` for notes. */
export function Field({
  label,
  value,
  onChange,
  error,
  hint,
  required,
  multiline,
  placeholder,
  keyboardType,
  autoCapitalize,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  error?: string;
  hint?: string;
  required?: boolean;
  multiline?: boolean;
  placeholder?: string;
  keyboardType?: "default" | "email-address" | "phone-pad" | "number-pad";
  autoCapitalize?: "none" | "words" | "sentences";
}) {
  return (
    <TextField isRequired={required} isInvalid={!!error}>
      <Label>{label}</Label>
      {multiline ? (
        <TextArea value={value} onChangeText={onChange} placeholder={placeholder} />
      ) : (
        <Input
          variant="secondary"
          value={value}
          onChangeText={onChange}
          placeholder={placeholder}
          keyboardType={keyboardType}
          autoCapitalize={autoCapitalize}
        />
      )}
      {hint && !error ? <Description>{hint}</Description> : null}
      <FieldError>{error}</FieldError>
    </TextField>
  );
}

/** A date as YYYY-MM-DD, with a one-tap "Today". Empty means not recorded. */
export function DateField({
  label,
  value,
  onChange,
  error,
  hint,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  error?: string;
  hint?: string;
}) {
  const valid = value === "" || /^\d{4}-\d{2}-\d{2}$/.test(value);
  return (
    <TextField isInvalid={!!error || !valid}>
      <Label>{label}</Label>
      <View className="flex-row gap-2 items-center">
        <View className="flex-1">
          <Input
            variant="secondary"
            value={value}
            onChangeText={onChange}
            placeholder="YYYY-MM-DD"
            keyboardType="numbers-and-punctuation"
            maxLength={10}
          />
        </View>
        <Button size="sm" variant="secondary" onPress={() => onChange(malaysiaDate())}>
          Today
        </Button>
      </View>
      {hint && !error ? <Description>{hint}</Description> : null}
      <FieldError>{error ?? (!valid ? "Use YYYY-MM-DD, e.g. 2026-10-05" : "")}</FieldError>
    </TextField>
  );
}

/** An on/off setting with an explanation underneath. */
export function Toggle({
  label,
  hint,
  value,
  onChange,
}: {
  label: string;
  hint?: string;
  value: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <ControlField isSelected={value} onSelectedChange={onChange} className="py-1">
      <View className="flex-1 pr-3">
        <Label>{label}</Label>
        {hint ? <Description>{hint}</Description> : null}
      </View>
      <ControlField.Indicator>
        <Switch />
      </ControlField.Indicator>
    </ControlField>
  );
}
