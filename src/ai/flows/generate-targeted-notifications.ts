'use server';

/**
 * @fileOverview Generates targeted notifications for specific communities using an LLM tool.
 *
 * - generateTargetedNotifications - A function that generates targeted notifications.
 * - GenerateTargetedNotificationsInput - The input type for the generateTargetedNotifications function.
 * - GenerateTargetedNotificationsOutput - The return type for the generateTargetedNotifications function.
 */

import {ai} from '@/ai/genkit';
import {z} from 'genkit';

const GenerateTargetedNotificationsInputSchema = z.object({
  document: z.string().describe('The document to summarize into actionable notification items.'),
  community: z.string().describe('The specific community to target with the notification.'),
});
export type GenerateTargetedNotificationsInput = z.infer<typeof GenerateTargetedNotificationsInputSchema>;

const GenerateTargetedNotificationsOutputSchema = z.object({
  notificationItems: z.array(z.string()).describe('A list of actionable notification items for the community.'),
});
export type GenerateTargetedNotificationsOutput = z.infer<typeof GenerateTargetedNotificationsOutputSchema>;

export async function generateTargetedNotifications(
  input: GenerateTargetedNotificationsInput
): Promise<GenerateTargetedNotificationsOutput> {
  return generateTargetedNotificationsFlow(input);
}

const generateTargetedNotificationsPrompt = ai.definePrompt({
  name: 'generateTargetedNotificationsPrompt',
  input: {schema: GenerateTargetedNotificationsInputSchema},
  output: {schema: GenerateTargetedNotificationsOutputSchema},
  prompt: `You are a system administrator assistant responsible for generating targeted notifications for specific communities.

  Your task is to summarize the provided document into a list of actionable notification items tailored to the specified community.

  Document: {{{document}}}
  Community: {{{community}}}

  Please provide a list of concise and actionable notification items that are relevant to the community.`,
});

const generateTargetedNotificationsFlow = ai.defineFlow(
  {
    name: 'generateTargetedNotificationsFlow',
    inputSchema: GenerateTargetedNotificationsInputSchema,
    outputSchema: GenerateTargetedNotificationsOutputSchema,
  },
  async input => {
    const {output} = await generateTargetedNotificationsPrompt(input);
    return output!;
  }
);
