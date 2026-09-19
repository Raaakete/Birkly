package main

import (
	"errors"
	"os"
	"path/filepath"
	"strings"
)

func cmsRoot() (string, error) {
	exe, err := os.Executable()
	if err != nil {
		return "", err
	}
	resolved, err := filepath.EvalSymlinks(exe)
	if err == nil {
		exe = resolved
	}
	return resolveCMSRootFromExe(exe)
}

func resolveCMSRootFromExe(exePath string) (string, error) {
	exePath = filepath.Clean(exePath)
	candidates := cmsRootCandidates(exePath)
	for _, root := range candidates {
		if isCMSRoot(root) {
			return root, nil
		}
	}
	return "", errors.New("index.php not found — run Birkly Launch from your extracted Birkly folder (not inside the zip)")
}

func cmsRootCandidates(exePath string) []string {
	dir := filepath.Dir(exePath)
	out := []string{dir}

	// macOS app bundle: …/Birkly Launch.app/Contents/MacOS/birkly-launch → CMS root is parent of .app
	if filepath.Base(dir) == "MacOS" {
		contents := filepath.Dir(dir)
		if filepath.Base(contents) == "Contents" {
			appBundle := filepath.Dir(contents)
			if strings.HasSuffix(filepath.Base(appBundle), ".app") {
				out = append(out, filepath.Dir(appBundle))
			}
		}
	}
	return out
}

func isCMSRoot(root string) bool {
	if _, err := os.Stat(filepath.Join(root, "index.php")); err != nil {
		return false
	}
	if _, err := os.Stat(filepath.Join(root, "admin")); err != nil {
		return false
	}
	return true
}
