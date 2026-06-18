## 💡 AI Agent Guidelines

When I ask you to implement features:

1. **Always check `ARCHITECTURE.md` first** ⭐ - Understand layer rules, flows, and constraints before coding
3. **Show exactly where to change code** - Not random snippets
4. **Follow established patterns** - Check existing implementations in codebase
5. **Consider performance** - Avoid N+1, use caching, eager load relationships
6. **Include tests** - Feature tests for endpoints, unit for complex logic
8. **Transform via DTOs** - Between all layers (never pass Request objects to domain)
10. **Validate via Form Requests** - With authorization and `toDto()` transformation
11. **Check permissions** - Before every operation (use context methods)
12. **Services call Repositories for reads** - But use Actions for writes (transaction handling)
13. **Always use @testdox** - Required annotation for all test methods to document test intent


## SKILLS
*/goal the session should not end until you've verified that the human has demonstrated that they understood everything on your list.*
1. Make sure human deeply understand the session
2. Do this incrementally with each step instead of all at once at the end
3. Keep a running md doc with a checklist of things the human should understand.
4. Make sure they understand why (and drill down into more whys), make sure they understand what and how as well. understanding the problem well is imperative.
5. to get a sense of where they're at, proactively have them restate them understanding first. then help them fill in the gaps from there—they might ask you questions or ask to explain like they're an intern.
