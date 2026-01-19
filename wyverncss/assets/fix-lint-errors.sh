#!/bin/bash

# Fix unused imports in test files
files=(
  "src/admin/__tests__/components/BotAnalytics.test.tsx"
  "src/admin/components/RAGSettings/__tests__/RAGSettingsPanel.test.tsx"
  "src/admin/components/SemanticSearch/__tests__/SearchResult.test.tsx"
  "src/admin/components/SemanticSearch/__tests__/SemanticSearch.test.tsx"
  "src/admin/components/SourceAttribution/__tests__/SourceItem.test.tsx"
  "src/admin/hooks/__tests__/useConversationMemory.test.ts"
  "src/admin/hooks/__tests__/usePatternMatch.test.ts"
  "src/admin/hooks/__tests__/useSemanticSearch.test.ts"
)

for file in "${files[@]}"; do
  if [ -f "$file" ]; then
    # Remove unused waitFor import
    sed -i 's/, waitFor//g' "$file"
    sed -i 's/waitFor, //g' "$file"
    # Remove unused within import
    sed -i 's/, within//g' "$file"
    sed -i 's/within, //g' "$file"
    # Remove unused user variable
    sed -i '/const user = /d' "$file"
  fi
done

# Fix label-has-associated-control in RAGSettingsPanel.test.tsx
if [ -f "src/admin/components/RAGSettings/__tests__/RAGSettingsPanel.test.tsx" ]; then
  # Add htmlFor to labels
  sed -i 's/<label>/<label htmlFor="weaviate-url">/g' "src/admin/components/RAGSettings/__tests__/RAGSettingsPanel.test.tsx"
fi

# Fix redundant roles
for file in src/admin/components/SemanticSearch/*.tsx src/admin/components/SourceAttribution/*.tsx; do
  if [ -f "$file" ]; then
    sed -i 's/role="list"//g' "$file"
    sed -i 's/role="listitem"//g' "$file"
  fi
done

# Fix unused imports in hooks
sed -i '/getCloudServiceUrl/d' "src/admin/hooks/useRAG.ts" 2>/dev/null || true
sed -i "/'categoryLabels'/d" "src/admin/components/TemplateCustomizer.tsx" 2>/dev/null || true

echo "Fixed common linting errors"
