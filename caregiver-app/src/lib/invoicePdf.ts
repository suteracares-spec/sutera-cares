import { File, Paths } from "expo-file-system";
import { printToFileAsync } from "expo-print";
import { shareAsync } from "expo-sharing";

import { request } from "./api";

/**
 * Turns the printable invoice into a PDF on the phone and opens the share
 * sheet (WhatsApp, email…). The invoice never sits on a public link, and
 * the PDF is made in the app's cache and replaced on the next share.
 */
export async function shareInvoicePdf(path: string, token: string | null): Promise<void> {
  const { html, filename } = await request<{ html: string; filename: string }>(path, { token });
  const { uri } = await printToFileAsync({ html });

  // Give it a name the family recognises rather than a random one.
  const pdf = new File(uri);
  const named = new File(Paths.cache, filename);
  if (named.exists) named.delete();
  pdf.move(named);

  await shareAsync(named.uri, { mimeType: "application/pdf", dialogTitle: filename, UTI: "com.adobe.pdf" });
}
