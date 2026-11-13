# AI Contribution Policy

This document provides guidance for AI tools and developers using AI assistance when contributing to Mbin. It applies to all repositories under the [Mbin GitHub organization](https://github.com/MbinOrg).

This policy complements the existing [Contribution Guidelines](CONTRIBUTING.md). The requirements described there continue to apply in full - this document addresses how they extend to AI-assisted contributions.

## Requirements

### Disclosure

Every pull request containing AI-assisted code, documentation, or tests must declare this in the PR description. PRs found to have undisclosed AI use might be closed.

For full traceability at the commit level, each commit containing AI-assisted content must include an `Assisted-by:` git trailer:

```
Assisted-by: AGENT_NAME:MODEL_VERSION
```

The agent name and model version identify the AI tool. Basic development tools such as git, compilers, editors, and static analyzers shall not be listed - these are standard parts of any development workflow regardless of AI involvement.

The PR description disclosure explains how AI was used; the commit trailer ensures that provenance is permanently recorded in version history and available to future contributors, auditors, and tooling.
The commit trailer must be included in the merge-commit of the PR.

Examples:

```
Assisted-by: Devstral:devstral-small-2507
Assisted-by: ClaudeCode:claude-sonnet-4-6
Assisted-by: Qwen:qwen3-coder-32b
Assisted-by: Copilot:gpt-4o
```

### Author Accountability

The contributor is the legal and moral author of every line they submit. If a reviewer asks "why does this work this way?" and the answer is like "the AI wrote it," the PR will be closed. This applies to code, comments, documentation, and tests alike.
You must fully comprehend any content you submit and be able to explain, defend, and modify it.

### Human-Written Communication

Issues, PR descriptions, and review comments must be in the contributor's own words. Translation assistance and grammar/spelling help are acceptable exceptions and do not need to be disclosed.
The intent of this rule is to ensure that the ideas, reasoning, and decisions in community communication come from the contributor.

This requirement extends through the entire review process. Contributors must respond to reviewer questions and implement requested changes themselves. Passing maintainer feedback into an AI and posting whatever comes out is not an acceptable substitute for genuine engagement. If a contributor cannot explain or implement a requested change because they do not understand their own submission, the PR will be closed.

### Security and Dependency Scrutiny

AI tools hallucinate package names, produce subtly broken access controls, and may reproduce vulnerable patterns from their training data. Contributors must manually verify all dependencies, access control logic, authentication patterns, and security implications in AI-generated code before submitting.

Use of AI for finding security issues is **not** discouraged. \
AI-generated security reports must be independently verified before submission; unverified reports might be closed without response.

### No Autonomous Agent Submissions

AI agents must not open issues, submit pull requests, post review comments, or send security reports autonomously. Every contribution must be composed, reviewed, and submitted by a human. This includes agentic workflows where an AI browses the codebase, plans changes across multiple files, and generates commits. The human contributor remains responsible for reviewing all output before anything is submitted.

AI agents must not add `Signed-off-by` tags.

### Licensing and Copyright Compliance

Contributors must ensure AI-generated code contains no material from sources incompatible with the license of the repository or app they are contributing to. Each Mbin repository and software carries its own license - contributors are responsible for knowing which applies.

The applicability test has three parts: the AI tool's terms must permit open-source use of its output; no third-party copyrighted material may be reproduced; and any included material must use a compatible open-source license. If generated code appears identical or suspiciously similar to code from an incompatible source, it must be removed or replaced with an original implementation. Ignorance of AI-generated provenance is not a defense.

### Code Quality and Cleanup

AI output must be cleaned before submission. Dead code, redundant logic, excessive comments, inconsistent style, unused variables, malformed or garbled characters, structural drift, and unrelated file changes must all be removed. Submitting large AI code blobs without meaningful oversight - sometimes called "vibe coding" or "prompt dumping" - is prohibited. \
As stated under *Author Accountability* the contributor is fully responsible for the submitted code, so they shall review every generated line by themselves or rewrite AI suggestions by hand before submitting them.

Code comments must document the code itself - behavior, signatures, and constraints the code cannot express - never the process that produced it. Progress notes, decision records, and change descriptions ("changed X to Y", "as requested", "this fixes ...") belong in the commit message or PR discussion; in code they go stale and become misleading. Comments must stay brief and match the comment density of the surrounding code; and self-explanatory code needs no comment at all.

Duplicated logic is a further common sign of unreviewed AI output: existing helpers and utilities must be reused (and extended if required) instead of re-implemented. When a flawed pattern is corrected, every occurrence of it must be fixed - not only the one a reviewer happened to point out.

Signs of a disallowed submission include: large unreviewed AI blobs; obvious mechanical mistakes a human would fix in minutes; code that has clearly never been executed; and pull requests that shift debugging and cleanup work onto maintainers rather than the contributor.

New features must be tested at least on a development Mbin instance by the contributor before submission, and in every context in which they are reachable - not only the default view. AI-generated changes frequently work in the primary view while silently failing in secondary ones. Providing test instructions for an AI agent to execute is not a substitute for human testing.

---

## Guidelines

### Focused and Scoped Pull Requests

A pull request should address exactly one thing. AI-generated code frequently drifts in scope due to imprecise prompting, touching unrelated files or introducing incidental refactors. If a PR description does not match its diff, that is a signal the contributor did not review their own changes. Large changes must be broken into multiple focused commits or separate PRs.

### Maintainer Discretion

Maintainers have unreviewable authority to close AI-assisted contributions for quality, complexity, scope, or community-fit reasons. A contribution that costs reviewers more time than it returns value to the project is extractive and will be closed, regardless of how many rounds of review it has already received. The golden rule applies: a contribution should be worth more to the project than the time it takes to review.

### Beginner Issues

AI tools must not be used to fully automate the resolution of issues labeled `good first issue` or similar beginner-friendly labels. These issues exist to create a learning path for new contributors who will become future maintainers. Take a beginner issue only if you genuinely intend to learn and continue contributing to Mbin.

---

## Scope and Updates

This policy applies to all contributions to repositories and software under the Mbin GitHub organization, by all contributors. It will be reviewed and updated as AI tooling, open-source best practices, and applicable law evolve. Suggested changes are welcome via pull requests or our Matrix chat.
