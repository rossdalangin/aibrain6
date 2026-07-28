# Nexus AI Workforce: Frequently Asked Questions

## 1. General Questions
### Is Nexus AI just ChatGPT?
No. Nexus uses models like GPT-4o as an engine, but adds **Company Memory (RAG)**, **Multi-Agent Orchestration**, and **Native WordPress Actions**. It is a complete workforce management system, not a simple chat interface.

### Does it work with my theme?
Yes. Nexus runs inside the WordPress Admin and uses a React-based UI that is completely independent of your frontend theme.

## 2. Security & Privacy
### Where is my data stored?
Unlike most AI SaaS platforms, Nexus stores all your documents and conversation history on **your own server** in your WordPress database.

### Are my API keys safe?
Yes. API keys are encrypted using AES-256-CTR encryption before being stored. They are only decrypted in memory during an active API call.

## 3. Pricing
### Can I use my own API keys?
Yes. Nexus is designed for you to use your own keys, so you only pay for what you use at wholesale AI rates.

### What is "Agency Mode"?
Agency mode allows you to white-label the plugin, replacing our branding with yours, so you can sell AI services to your clients as a premium managed offering.

## 4. Subscriptions, Pricing, & Session Management
### What are the subscription pricing tiers?
Our premium plans are structured as follows:
- **Starter/Free:** `$0/mo` (1 active agent workspace).
- **Pro:** `$197/mo` (10 active agents, advanced RAG engine).
- **Agency:** `$497/mo` (100 active agents, client portals, white-label branding).
- **Enterprise:** `$997/mo` or Custom (unlimited agents, local Ollama hosting, Pinecone dedicated storage, custom SLAs).

### Can administrators manage the Enterprise subscription pricing?
Yes. Administrators can dynamically set and manage the Pro, Agency, and Enterprise subscription rates directly under **Nexus AI > License & Payments > Pricing & Gateways**. Changes automatically synchronize across all admin and client billing panels.

### How is the Enterprise plan fulfilled?
Fulfillment is handled cryptographically. When an administrator generates an Enterprise license key in the dashboard registry, the key is signed via a secure HMAC-SHA256 signature. Upon client-side activation, it instantly expands active employee limits to unlimited, unlocks local Ollama model options, and registers enterprise audit logging.

### How do I delete archive sessions?
Under **Nexus AI > Strategic Archive**, administrators can permanently delete old transcripts, completed meetings, or trace sequences by clicking the **Delete** button next to any archive record. This securely clears the conversation and all of its nested message records from your database.
