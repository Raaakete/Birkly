// Birkly local server launcher — easy-local path only.
// Does not replace start-server.bat/.sh, cloud upload, or XAMPP/Laragon flows.
package main

import (
	"bufio"
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strconv"
	"strings"
	"time"
)

const configFile = "settings/local-launcher.json"

type launcherConfig struct {
	Port         int    `json:"port"`
	OpenStartHere bool  `json:"open_start_here"`
	LastURL      string `json:"last_url,omitempty"`
}

func defaultConfig() launcherConfig {
	return launcherConfig{Port: 8080, OpenStartHere: true}
}

func main() {
	if len(os.Args) > 1 {
		switch os.Args[1] {
		case "-h", "--help":
			printHelp()
			return
		case "--setup":
			runSetup()
			return
		case "--port":
			if len(os.Args) < 3 {
				fatal("usage: --port <number>")
			}
			p, err := strconv.Atoi(os.Args[2])
			if err != nil || p < 1024 || p > 65535 {
				fatal("port must be 1024–65535")
			}
			root, err := cmsRoot()
			if err != nil {
				fatal(err.Error())
			}
			cfg := loadConfig(root)
			cfg.Port = p
			saveConfig(root, cfg)
			fmt.Printf("Saved port %d to %s\n", p, configFile)
			return
		case "--open":
			root, err := cmsRoot()
			if err != nil {
				fatal(err.Error())
			}
			cfg := loadConfig(root)
			openBrowser(projectURL(root, cfg.Port, cfg.OpenStartHere))
			return
		}
	}

	runServer()
}

func printHelp() {
	fmt.Println(`Birkly Launch — local built-in PHP server

Double-click to start (or run with no arguments). Keep this window open while using Birkly.
After a reboot, double-click again to restart the server.

  --setup          Change port and browser settings
  --port <n>       Set port (saved for next start)
  --open           Open your project URL in the browser
  -h, --help       This help

macOS: Birkly Launch.app · Windows: Birkly Launch.exe
Advanced: start-server.bat / ./start-server.sh still work the same way.`)
}

func configPath(root string) string {
	return filepath.Join(root, configFile)
}

func loadConfig(root string) launcherConfig {
	cfg := defaultConfig()
	path := configPath(root)
	data, err := os.ReadFile(path)
	if err != nil {
		return cfg
	}
	if err := json.Unmarshal(data, &cfg); err != nil {
		return defaultConfig()
	}
	if cfg.Port < 1024 || cfg.Port > 65535 {
		cfg.Port = 8080
	}
	return cfg
}

func saveConfig(root string, cfg launcherConfig) {
	_ = os.MkdirAll(filepath.Join(root, "settings"), 0o755)
	path := configPath(root)
	data, _ := json.MarshalIndent(cfg, "", "  ")
	_ = os.WriteFile(path, append(data, '\n'), 0o644)
}

func findPHP(root string) (string, error) {
	candidates := []string{}
	if runtime.GOOS == "windows" {
		candidates = []string{
			filepath.Join(root, "runtime", "php", "php.exe"),
			filepath.Join(root, "runtime", "php-win", "php.exe"),
		}
	} else {
		candidates = []string{
			filepath.Join(root, "runtime", "php", "bin", "php"),
			filepath.Join(root, "runtime", "php-mac", "bin", "php"),
		}
	}
	for _, c := range candidates {
		if st, err := os.Stat(c); err == nil && !st.IsDir() {
			return c, nil
		}
	}
	if p, err := exec.LookPath("php"); err == nil {
		return p, nil
	}
	return "", errors.New("PHP not found — use check-php.bat / ./check-php.sh, or install PHP (easy path: bundled runtime/php in a future download)")
}

func portFree(port int) bool {
	ln, err := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port))
	if err != nil {
		return false
	}
	_ = ln.Close()
	return true
}

func projectURL(root string, port int, startHere bool) string {
	path := "/admin/"
	if startHere {
		path = "/0_START-HERE.html"
	}
	return fmt.Sprintf("http://localhost:%d%s", port, path)
}

func openBrowser(url string) {
	var cmd *exec.Cmd
	switch runtime.GOOS {
	case "windows":
		cmd = exec.Command("cmd", "/c", "start", "", url)
	case "darwin":
		cmd = exec.Command("open", url)
	default:
		cmd = exec.Command("xdg-open", url)
	}
	_ = cmd.Start()
}

func runSetup() {
	root, err := cmsRoot()
	if err != nil {
		fatal(err.Error())
	}
	cfg := loadConfig(root)
	reader := bufio.NewReader(os.Stdin)
	fmt.Printf("Current port: %d\n", cfg.Port)
	fmt.Print("New port (Enter to keep): ")
	line, _ := reader.ReadString('\n')
	line = strings.TrimSpace(line)
	if line != "" {
		p, err := strconv.Atoi(line)
		if err != nil || p < 1024 || p > 65535 {
			fatal("invalid port")
		}
		cfg.Port = p
	}
	fmt.Print("Open 0_START-HERE.html on start? [Y/n]: ")
	line, _ = reader.ReadString('\n')
	line = strings.TrimSpace(strings.ToLower(line))
	cfg.OpenStartHere = line != "n" && line != "no"
	saveConfig(root, cfg)
	fmt.Printf("Saved. Project URL: %s\n", projectURL(root, cfg.Port, cfg.OpenStartHere))
}

func runServer() {
	ensureDarwinInteractiveTerminal()
	root, err := cmsRoot()
	if err != nil {
		fatal(err.Error())
	}
	cfg := loadConfig(root)
	php, err := findPHP(root)
	if err != nil {
		fmt.Println(err)
		if runtime.GOOS == "windows" {
			helper := filepath.Join(root, "install-php-windows.bat")
			if _, statErr := os.Stat(helper); statErr == nil {
				fmt.Println("Opening install-php-windows.bat …")
				_ = exec.Command("cmd", "/c", "start", "", helper).Start()
			}
		}
		waitExit(1)
	}

	port := cfg.Port
	if !portFree(port) {
		fmt.Printf("Port %d is in use. Trying %d …\n", port, port+1)
		if portFree(port + 1) {
			port = port + 1
			cfg.Port = port
			saveConfig(root, cfg)
		} else {
			fatal(fmt.Sprintf("ports %d and %d are busy — run with --setup to pick another port", cfg.Port, cfg.Port+1))
		}
	}

	router := filepath.Join(root, "server-router.php")
	cmd := exec.Command(php, "-S", fmt.Sprintf("localhost:%d", port), "-t", root, router)
	cmd.Dir = root
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	if err := cmd.Start(); err != nil {
		fatal("could not start PHP: " + err.Error())
	}

	url := projectURL(root, port, cfg.OpenStartHere)
	cfg.LastURL = url
	saveConfig(root, cfg)

	fmt.Println()
	fmt.Println("  Birkly local server is running")
	fmt.Printf("  %s\n", url)
	fmt.Println()
	fmt.Println("  Keep this window open. Close it or press Enter to stop.")
	fmt.Println("  Change port later: run the launcher with --setup (or use start-server.sh / .bat)")
	fmt.Println()

	openBrowser(url)

	_, _ = bufio.NewReader(os.Stdin).ReadBytes('\n')

	_ = cmd.Process.Signal(os.Interrupt)
	done := make(chan error, 1)
	go func() { done <- cmd.Wait() }()
	select {
	case <-done:
	case <-time.After(3 * time.Second):
		_ = cmd.Process.Kill()
	}
}

func fatal(msg string) {
	fmt.Fprintln(os.Stderr, "[Birkly Launch]", msg)
	waitExit(1)
}

func waitExit(code int) {
	if runtime.GOOS == "windows" || (runtime.GOOS == "darwin" && stdinIsTerminal()) {
		fmt.Println("Press Enter to close…")
		_, _ = bufio.NewReader(os.Stdin).ReadBytes('\n')
	}
	os.Exit(code)
}
