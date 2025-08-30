

**Question MRD**
Looking at the mrd.md, come up with 4 reasons why this might not be the most efficient, DRY, YAGNI or scaleable solution, and then play devil's advocate against those 4 reasons. Finally, with all the information now available, evaluate if any changes to the mrd.md document is warranted.


**question implementation**
If you look at the current codebase and the @mrd.md document, and read between the lines of the MRD, are there things you would have done differently, improvements you would have made to meet some un-stated, but likely, objective? Don't change any code, just provide an assessment a world-class expert.




**Review recent code changes**
You are a 10× full-stack engineer LLM (TypeScript, Express, React, Vite).

Context (C): Use the code blocks/files produced or edited in the latest specific feature development or/and debugging  chat session as your input.  
Objective (O): Review that code for duplicate logic, patches over root causes, and technical debt, then autonomously refactor it for DRY, YAGNI, maximal simplicity, robustness, and future-proof design.  
Style (S): Formal, example-driven with clear before/after code snippets.  
Tone (T): Highly technical and directive.  
Audience (A): An AI collaborator that will apply these changes.  

Response Format (R): Produce exactly four labeled sections in this order:

1. **Initial Evaluation (chain-of-thought)**  
   - Step-by-step reasoning, referencing the provided code.  
   - List each detected issue with a short excerpt from the target code.

2. **Structured Issue Outline**  
   - Group by module/component.  
   - For each issue:  
     a. Title  
     b. Brief description  
     c. Severity (High/Med/Low)

3. **Refactoring Details**  
   - For every issue, show:  
     1. Before snippet (from the provided code)  
     2. After snippet (diff or side-by-side)  
     3. Rationale tied to DRY/YAGNI and root-cause elimination  
     4. A test or verification step to ensure no breakage

4. **Final Summary & Next Steps**  
   - High-level bullet list of all changes  
   - Metrics (lines added/removed, new tests) and estimated risk level  
   - A “Next Steps” prompt asking for approval before merging

Strictly follow these steps against the last session’s final code. Do not reintroduce complexity or omit any verification step.

Begin now with **1. Initial Evaluation (chain-of-thought)**:




# Refined Prompt Patterns

##dig deeper into a bug##
question your assumptions, quiet your ego, and dig 2 levels deeper to confirm your proposed bug identification and fix. Validate like a 10x engineer.


# 5 why's


Act like a Toyota Production System engineer and a 10× full-stack developer. For every bug you identify, follow these steps:

1. **Quiet your ego & question assumptions.**  
2. **5 Whys root-cause analysis:**  
   - Ask **“Why?”** 5 times, documenting each answer to trace the chain to the true root cause by looking at code and logs. IMPORTANT: Only make hard-data validated conclusions.
3. **Dig deeper:** If you don't have a 100% sure conclusion, push additional “why?” questions if needed until you have a resolution.  
4. **Validate like a 10× engineer:**  
   - Confirm your diagnosis with reproducible steps. If you can't validate your diagnosis 100%, start over again with more "why" questions until your diagnosis is confirmed.
5. **Implement fix:**  
   - Propose a fix that is DRY & YAGNI, then outline tests or checks that prove root-cause resolution (not just a patch).

Begin every evaluation with this Toyota‐style, iterative “why?” audit before suggesting any code changes.



## optimize bug fix
You’ve fixed the bug—well done. Now go deeper: confirm all your solutions are a true root-cause resolution, not just quick patches. Remove duplicated logic and orphaned code (DRY), and strip out any features you don’t actually need (YAGNI). Question every assumption and set your ego aside. Drill at least two layers down: hunt for hidden technical debt, conflicting fragments, or edge-case leaks. Validate your solution like a top-tier engineer—clean, maintainable, and built for long-term stability. Don't fix what is not broken.



## Core Prompting Techniques
- **Conciseness & Clarity:** Favor minimal, self-documenting code; reduce lines without sacrificing readability or maintainability.
- **Senior Developer Mindset:** Act as a 10x engineer—prioritize best practices, robust architecture, and debt-free solutions.
- **Completion Guarantee:** Persist until a fully working, tested solution is delivered; do not stop mid-task.

## Error Diagnosis & Resolution
### Difficult Error Workflow
1. **Analyze (4 paragraphs):** Explore potential root causes; question assumptions.
2. **Propose (4 fixes):** Outline distinct fixes, referencing prior attempts.
3. **Select & Justify:** Choose the optimal fix based on impact and simplicity.
4. **Review & Optimize:** Perform a final code review for bugs, edge cases, and performance; deliver runnable code.

## Chat Handoff Summary
- **Goal:** Brief a new developer on project status in short & concise  paragraphs covering:
  1. Completed work and outcomes
  2. Failures, open issues, and lessons learned
  3. Files changed, if that matters for future development, key insights, and “gotchas” to avoid
  4. Key files and directories
- **Tone:** Technical README style—fact-only, no speculation or fluff. Make sure it is DRY & YAGNI.

## Solution Evaluation & Implementation
1. **Evaluate 4 Strategies:** Against complexity, **DRY**, **YAGNI**, and scalability.
2. **Describe Each:** One paragraph per strategy, detailing trade-offs.
3. **Compare & Choose:** Summarize side-by-side; pick best overall.
4. **Implement:** Provide runnable, concise code aligned with chosen approach.
5. **Note:** Don't fix what is not broken


## Ongoing Code Review

Just as a sanity check, Let us take a step back and look at all the code changes you have made thoughout this coding session from beinning to end. Given what you now know:
- **Objectives:** Spot logic flaws, edge cases, performance issues, technical debt.
- **Refactoring Options:** Without changing your solution, propose 4 strategies, each with cognitive, performance, DRY, YAGNI, and scalability analysis, with a focus wordpress and Storefront theme and php best practices.
- **Recommendation & Fix:** Compare, select, and apply the best refactoring in code like a 10x engineer.
- **Validate:** Make sure all your recommendations are supported by validated problems and fixes, and not assumptions.
- **IMPORTANT:** Don't over-engineer, and don't fix what is not broken

## Bug Fix Procedure

Let's try to get rid of these bugs:
1. **Review Attempts:** Examine and avoid repeating prior fixes.
2. **Diagnose (4 causes):** Link each to specific symptoms or code.
3. **Propose Fixes:** One concise fix per cause, enforcing DRY/YAGNI, like a 10x engineer.
4. **Select & Implement:** Choose most plausible, update code.
5. **Verify:** Describe tests to confirm resolution and avoid regressions.

## Implementing Suggestions
- Apply requested changes; verify code correctness through three internal checks.
- Deliver optimal code with minimal lines and clear structure.

## Learning from Mistakes
- **Trace & Document:** Identify past errors, corrections, and insights.
- **Consolidate:** Update `@tests.mdc` with lessons to prevent repeat mistakes.

## TypeScript Project Audit
- **Review Area:** Type safety, syntax/style, modularity, architecture, performance.
- **Deliverables:**
  1. **Findings:** Gaps and inconsistencies per module.
  2. **Code Fixes:** `diff` or snippet updates with rationale.
  3. **Action Plan:** Prioritized list of improvements.
  4. **Tooling Suggestions:** ESLint/Prettier or extensions.


**From Feature Description → User Stories**

Prompt:

“Given this feature brief: <your description>, generate 3–5 user stories with clear acceptance criteria (Gherkin if possible).”

When to use: before breaking work into tickets.


**Architecture Sketch & Trade-offs**

Prompt:

“Outline a high-level architecture for <project/feature>. Include component diagram, data flow, and at least two technology-stack options. Analyze pros/cons of each.”

When to use: at design kickoff or major scope changes.


**Performance Profiling & Optimization**

Prompt:

“Suggest profiling tools/methods for <language/stack> and identify 3 hotspots to watch. Recommend targeted optimizations and benchmarks to validate.”

When to use: during load tests or if latency/memory is too high.


**Automated Test Generation**

Prompt:

“Generate unit tests (Jest/Mocha/PHPUnit/PyTest) for <module/function>. Cover normal, edge, and error cases. Ensure ≥80% coverage.”

When to use: right after implementing business logic.

**CI/CD Pipeline Configuration**

Prompt:

“Provide a CI/CD config (.github/workflows/ci.yml, GitLab CI, CircleCI, etc.) that runs lint, tests, build, and deploy for <stack>. Include rollout and rollback steps.”

When to use: when automating build/test/deploy.


**Monitoring, Logging & Alerts**

Prompt:

“List key metrics (throughput, error rate, latency) and propose alert thresholds. Suggest logging structure and dashboards (Grafana/Datadog).”

When to use: before or right after deployment.


**leaning up tests**
i want to you to review all tests, one by one, and as your are analyzing each test, look at all the other tests to see if there is some redundancies and opporunities for consolidation. If needed, consolidate tests for DRY and YAGNI.

**adding tests**
Make sure the new functionality have the approriate tests. Check first if the functionality is fully or partially covered by another test. Consolidate tests for DRY and YAGNI.

*refactoring*
You are an expert full-stack engineer and architect in a TypeScript monorepo (React, Vite, Node.js, Express) with Vitest/Jest tests. Your mission: deeply refactor the most complex, longest files—no superficial tweaks.

## 1. Context
- **Monorepo** with frontend (TS, React, Vite) and backend (TS, Node, Express).  
- Existing test suites: Vitest (frontend), Jest (backend).  
- Code is available in full; refactors will guide other LLMs and senior engineers.

## 2. Goals
1. **Identify** top candidates by LOC and cyclomatic complexity.  
2. **Diagnose** architectural, type-safety, DRY-compliance, and test-coverage gaps.  
3. **Plan** a clean, YAGNI-aware, modular, test-safe refactor strategy.  
4. **Execute** IMPORTANT: a single "Next Minimal Refactor"—the smallest meaningful change.  
5. **Log** progress in a structured "Refactoring Log."

## 3. Constraints
- Must adhere to modern TypeScript best practices.  
- Enforce **DRY** (Don't Repeat Yourself) to remove duplication.  
- Apply **YAGNI** (You Aren't Gonna Need It): avoid over-engineering.  
- Avoid non-incremental or cosmetic changes.  
- Each change must be testable and backward-compatible.
- **Map dependencies before changing widely-used files** to prevent breaking changes.
- **Preserve existing functionality** - verify critical flows work after each change.

## 4. Deliverable: `refactoring_plan.md`
Produce **one** markdown file per target file, following this template:

```markdown
# Refactoring Plan for [path/to/file.ts]

## File Analyzed
[path/to/file.ts]

## Metrics
- **Lines of Code**: [LOC]
- **Cyclomatic Complexity**: [value]
- **Role**: [e.g. route controller, state manager]

## Dependency Analysis
- **Direct Consumers**: [List files that import this file - check with grep/search]
- **Risk Level**: [High/Medium/Low - High if 3+ files depend on its exports]

## Problems Identified
- [ ] Missing/weak TypeScript types
- [ ] SRP violations
- [ ] Mixed UI vs. logic (frontend)
- [ ] Coupled data access and business logic (backend)
- [ ] Duplication breaches DRY
- [ ] Over-engineered code violating YAGNI
- [ ] Insufficient test coverage
- [ ] Non-deterministic behavior

## High-Level Strategy
1. Extract pure utilities; eliminate duplicate logic (DRY).  
2. Enforce strict interfaces and minimal surface area (YAGNI).  
3. Isolate side-effects.  
4. Expand Vitest/Jest coverage.  
5. Document each step.

## Next Minimal Refactor
> Choose the smallest change that fixes/isolates a major issue, is test-covered, won't break adjacent code, and avoids unnecessary complexity (YAGNI).
> **If Risk Level is High**: Extract individual functions first rather than restructuring the entire file.

**Example**  
- Extract and type `parseUserToken()` from `auth.ts` into `lib/token.ts`.  
- Write Jest tests for edge cases.
- **Verify no existing imports break** before proceeding.

## Refactoring Log
- [ ] [YYYY-MM-DD] Planned: Extract `parseUserToken()` → `lib/token.ts` + tests  
- [ ] [YYYY-MM-DD] TODO: Replace inline logic in `/routes/login.ts`
```

## 5. Implementation Protocol
1. **Before making changes**: Run dependency analysis with `grep -r "import.*filename" .` 
2. **Start with lowest-risk changes**: Extract utilities before modifying core infrastructure
3. **Test incrementally**: Verify functionality after each minimal change
4. **If 3+ attempts fail on same file**: Stop and reassess approach - file may be too complex for current refactor
