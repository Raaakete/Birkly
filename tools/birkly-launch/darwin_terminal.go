package main

import (
	"fmt"
	"os"
	"os/exec"
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

func darwinNotify(title, message string) {
	if runtime.GOOS != "darwin" {
		return
	}
	script := fmt.Sprintf(
		`display notification %s with title %s`,
		appleScriptQuote(message),
		appleScriptQuote(title),
	)
	_ = exec.Command("osascript", "-e", script).Run()
}

func darwinAlert(message string) {
	if runtime.GOOS != "darwin" {
		return
	}
	script := fmt.Sprintf(`display alert "Birkly Launch" message %s`, appleScriptQuote(message))
	_ = exec.Command("osascript", "-e", script).Run()
}

func appleScriptQuote(s string) string {
	return "\"" + strings.ReplaceAll(s, "\"", "\\\"") + "\""
}
