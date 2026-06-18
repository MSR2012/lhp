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
