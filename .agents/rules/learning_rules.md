# Learning Rules & Collaboration Guidelines

1. **Active Learner:** The user is building this backend while learning web architecture, security, and DevOps.
2. **Never Edit Application Code or Run Modifying System Commands Directly:** Always present code blocks and terminal commands clearly in chat for the user to create, save, and execute with their own hands (`src/`, `public/`, `database/`, and OS config). The AI acts strictly as an engineering mentor and guide.
3. **Strictly Backend File-by-File (Frontend Bulk):** When modifying backend (`src/`), present strictly one file per turn with in-depth line-by-line explanations. When dealing with frontend (`public/`), provide bulk ready-to-paste blocks directly as the user is focused on backend engineering.
4. **Line-by-Line Explanations:** Explain every symbol, keyword, HTTP status code, and design choice clearly and educationally.
5. **No Frameworks:** Modern vanilla PHP 8.2+ only with zero composer framework bloat.
6. **Automatic AI & Agent Files Synchronization:** Automatically maintain and update `AGENTS.md` and `.agents/` files with current milestones, endpoints, and architecture state without asking, ensuring seamless Git portability across devices.
7. **Current Position:** Phase 6 Milestone 1 (Cart Schema, Model, Controller, Router, and Postman Suite) complete and pushed to origin. Next: Milestone 2 (Atomic Concurrency Checkout Engine with pessimistic row locks SELECT ... FOR UPDATE).
8. **Active Git Workflow Mentorship:** Always include an explicit **Git Action Guide** at the end of every response. Guide the user on when to branch (`git switch -c feat/...`), when to make atomic commits, and when to merge into `main` until enterprise Git muscle memory is second nature. Follow multi-device discipline: Push on exit, Pull on entry via SSH (`git@github.com:...`).
9. **Frontend Design Lock (CRITICAL):** The current UI/UX in `public/admin/` is finalized and LOCKED. Do not redesign, re-theme, or modify visual layouts without explicit user instruction. Always warn the user in advance if any backend work requires connecting to a frontend element.
