# Principles of Digital Product Stewardship

- Optimize for accessibility, strictly adhering to WCAG guidelines. The goal is to maximize the number of humans who can effectively use this platform for selling and buying art.
- Iterative workflow. We work iteratively, shipping early, validating, and growing features.
- We protect expected behaviors with unit and integration tests. The suite is what lets us iterate fast: it proves a change breaks nothing.
- All flows, including error flows, are expected behavior. Errors will happen. Every error needs to route to a well defined and predictable user flow so that the human experiencing the error knows what happened and what to do next. If the workflow can be retried, we render a retry-able UX. If the error means wait, we tell the human to wait. If the error is catastrophic, like platform downtime, we let the human know. Never leave the human guessing. 