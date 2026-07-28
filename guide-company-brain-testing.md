# Nexus AI Workforce: Company Brain (RAG Engine) Testing & Verification Guide

This guide explains how to upload, test, and verify the multi-tiered **Company Brain (RAG Engine)** using our newly created premium sample files.

---

## Step 1: Ingesting the Sample Brain Files
To "feed" your AI workforce with corporate context, navigate to **Nexus AI > Company Brain**:

1. **Option A: File Drag & Drop**
   - Locate the sample files on your local drive:
     * `project-docs/samples/brain-refund-policy.txt`
     * `project-docs/samples/brain-seo-checklist.txt`
   - Under **Ingest Data**, ensure **Assign Knowledge To** is set to **Global Company Brain**.
   - Drag and drop or upload `brain-refund-policy.txt`.
   - Once the loader finishes, repeat the process and upload `brain-seo-checklist.txt`.

2. **Option B: Direct Copy-Paste**
   - Open `project-docs/samples/brain-refund-policy.txt` in a text editor and copy its contents.
   - On the Company Brain page, locate the **Direct Intelligence Input** form.
   - Enter `Refund and Subscription SOP` in the **Document Name** input.
   - Paste the copied text inside the textarea, and click **Ingest Strategic Text**.
   - Repeat the same for `brain-seo-checklist.txt` naming it `SEO Optimization Checklist`.

---

## Step 2: Verifying Ingestion via the Document Library
On the right-hand side of the Company Brain page, look at the **Document Library** table:
- Verify that your newly uploaded document names are visible.
- The **Status** column should display a green **INDEXED ✓** badge.
- This indicates that the RAG engine has successfully:
  1. Parsed the raw text document.
  2. "Chunked" it into semantic fragments.
  3. Committed them into the local WordPress database (`{$wpdb->prefix}ai_knowledge_chunks` table).

---

## Step 3: Performing Semantic Search & Testing in the Playground
To prove that your AI employees can successfully retrieve and use this brain data, let's conduct testing in the **Agent Playground**:

1. Navigate to **Nexus AI > Agent Playground**.
2. Select an active expert to consult, such as **Sarah (CMO)** or **Alexander (CEO)**.

### Test Case 1: Testing Refund Policies & Cancellation Rules
- **Prompt input:** *"Can a Pro Plan customer cancel their subscription and get their money back on Day 10?"*
- **What to look for in the AI's response:**
  - The AI should reference that subscriptions have a **14-day Risk-Free Guarantee**, and since it is **Day 10**, they are eligible for a **100% refund**.
  - It should also correctly explain the **Subscription Cancellation Rules**: that when they cancel, they retain active access until the end of their period, at which point the system removes the local cryptographic licensing signature and downgrades them to the **Starter/Free tier** without breaking their existing files or database records.

### Test Case 2: Testing SEO Content Guidelines
- **Prompt input:** *"What is our standard keyword density, and how long should our sentences and paragraphs be?"*
- **What to look for in the AI's response:**
  - The AI should reference that the primary keyword must be in the H1, the first 100 words, and in at least two H2/H3 subheadings.
  - It must state that the **keyword density must remain strictly between 1.2% and 1.8%** (and is flagged for rewrites if it exceeds 2.0%).
  - It should specify paragraph lengths of **3-4 sentences max (under 60 words)** and sentence lengths of **under 20 words**.

---

## Step 4: Technical Verification (For Developers)
If you want to verify that the RAG Engine did its semantic lookup and injected the context correctly behind the scenes, you can inspect the WP database.

You can execute a simple SQL query inside your WordPress Database Manager (such as phpMyAdmin) to verify that chunk records exist:

```sql
SELECT * FROM wp_ai_knowledge_documents ORDER BY id DESC LIMIT 5;
SELECT * FROM wp_ai_knowledge_chunks ORDER BY id DESC LIMIT 5;
```

You can also run a quick PHP snippet via WP-CLI or inside a test plugin file to manually test the chunk retriever search:

```php
<?php
// Include WordPress configuration files
require_once('wp-load.php');

$searcher = new \NexusAI\Workforce\AI\RAG\Searcher();
$query = "What is the sentence length limit for SEO?";

// Query the RAG engine for matched chunks
$chunks = $searcher->search($query, 3); // retrieve top 3 semantic matches

echo "RAG CHUNKS FOUND:\n";
foreach ($chunks as $chunk) {
    echo "- " . $chunk->content . "\n";
}
?>
```
================================================================================
