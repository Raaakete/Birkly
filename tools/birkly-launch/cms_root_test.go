package main

import (
	"os"
	"path/filepath"
	"testing"
)

func TestResolveCMSRootFromExe_flatBinary(t *testing.T) {
	root := t.TempDir()
	_ = os.WriteFile(filepath.Join(root, "index.php"), []byte("<?php"), 0o644)
	_ = os.Mkdir(filepath.Join(root, "admin"), 0o755)

	exe := filepath.Join(root, "birkly-launch-mac")
	got, err := resolveCMSRootFromExe(exe)
	if err != nil {
		t.Fatal(err)
	}
	if got != root {
		t.Fatalf("got %q want %q", got, root)
	}
}

func TestResolveCMSRootFromExe_macAppBundle(t *testing.T) {
	root := t.TempDir()
	_ = os.WriteFile(filepath.Join(root, "index.php"), []byte("<?php"), 0o644)
	_ = os.Mkdir(filepath.Join(root, "admin"), 0o755)

	appRoot := filepath.Join(root, "Birkly Launch.app", "Contents", "MacOS")
	if err := os.MkdirAll(appRoot, 0o755); err != nil {
		t.Fatal(err)
	}
	exe := filepath.Join(appRoot, "birkly-launch")

	got, err := resolveCMSRootFromExe(exe)
	if err != nil {
		t.Fatal(err)
	}
	if got != root {
		t.Fatalf("got %q want %q", got, root)
	}
}

func TestCmsRootCandidates(t *testing.T) {
	exe := "/Users/me/Birkly/Birkly Launch.app/Contents/MacOS/birkly-launch"
	cands := cmsRootCandidates(exe)
	if len(cands) < 2 {
		t.Fatalf("expected app + flat candidates, got %v", cands)
	}
	wantCMS := "/Users/me/Birkly"
	if cands[1] != wantCMS {
		t.Fatalf("cms root candidate %q want %q", cands[1], wantCMS)
	}
}
