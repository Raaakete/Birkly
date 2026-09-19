package main

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
)

func stdinIsTerminal() bool {
	fi, err := os.Stdin.Stat()
	if err != nil {
		return false
	}
	return (fi.Mode() & os.ModeCharDevice) != 0
}

// When the .app is opened from Finder there is no TTY — open Terminal and re-run there.
func ensureDarwinInteractiveTerminal() {
	if runtime.GOOS != "darwin" || stdinIsTerminal() {
		return
	}
	exe, err := os.Executable()
	if err != nil {
		return
	}
	resolved, err := filepath.EvalSymlinks(exe)
	if err == nil {
		exe = resolved
	}
	root, err := cmsRoot()
	if err != nil {
		return
	}
	shellLine := fmt.Sprintf("cd %s && exec %s", shellSingleQuote(root), shellSingleQuote(exe))
	script := fmt.Sprintf(`tell application "Terminal" to activate
tell application "Terminal" to do script %s`, appleScriptQuote(shellLine))
	if err := exec.Command("osascript", "-e", script).Run(); err != nil {
		return
	}
	os.Exit(0)
}

func shellSingleQuote(s string) string {
	return "'" + strings.ReplaceAll(s, "'", "'\\''") + "'"
}

func appleScriptQuote(s string) string {
	return "\"" + strings.ReplaceAll(s, "\"", "\\\"") + "\""
}
