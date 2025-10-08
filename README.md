# 🌙 Smart Sleep Manager for Unraid

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Unraid](https://img.shields.io/badge/Unraid-6.12%2B-orange.svg)](https://unraid.net)
[![GitHub release](https://img.shields.io/github/release/skycryer/smart-sleep-manager.svg)](https://github.com/skycryer/smart-sleep-manager/releases)
[![GitHub issues](https://img.shields.io/github/issues/skycryer/smart-sleep-manager.svg)](https://github.com/skycryer/smart-sleep-manager/issues)

An intelligent automated sleep management plugin for Unraid servers with array disk monitoring, network activity detection, and Telegram notifications.

## ✨ Features

🛌 **Intelligent Sleep Management**
- Monitors array disk activity (standby/spindown detection)
- Network traffic monitoring with configurable thresholds
- Configurable idle time before sleep activation
- Distinguishes between array disks and cache/docker/parity drives

📱 **Telegram Integration**
- Real-time notifications for sleep events
- Configurable notification types (standby timer, sleep activation, blocked sleep)
- Test function built into web interface
- Markdown-formatted messages with server information

🎛️ **Web-Based Configuration**
- Complete settings management through Unraid web interface
- Auto-detection of available disks and network interfaces
- Input validation and real-time testing
- No command-line configuration required

🔧 **Advanced Power Management**
- Dynamix S3 Sleep compatibility (recommended method)
- Alternative systemctl suspend support
- Automatic Wake-on-LAN configuration
- Post-wake Samba restart for SMB share reliability
- Optional gigabit speed forcing and DHCP renewal

⏰ **Automated Scheduling**
- Runs every 5 minutes via cron job
- Automatic cron job management (install/uninstall)
- Manual sleep check function for testing
- Comprehensive logging for troubleshooting

## 📋 Requirements

- **Unraid**: Version 6.12.0 or higher
- **Hardware**: S3 sleep-capable motherboard/BIOS
- **Network**: Wake-on-LAN compatible network interface
- **Optional**: Telegram account for notifications

## 🚀 Quick Start

### 1. Installation

**Method A: Direct Plugin Installation**
```bash
# Download and install via Unraid Web UI
# Apps > Install Plugin > Plugin File URL:
https://raw.githubusercontent.com/skycryer/smart-sleep-manager/main/smart.sleep.manager.plg
```

**Method B: Manual Download**
```bash
# Download the plugin file and install via web interface
wget https://github.com/skycryer/smart-sleep-manager/releases/latest/download/smart.sleep.manager.plg
# Then: Apps > Install Plugin > Choose File
```

### 2. Basic Configuration

1. Navigate to **Settings > Smart Sleep Manager**
2. **Enable** the plugin
3. Set **Array Disks** to monitor: `sdb sdc sdd sde` (your array disks)
4. Set **Ignore Disks**: `cache1 cache2 parity1` (cache/docker/parity)
5. Configure **Idle Time**: `15` minutes (default)
6. Click **Apply**

### 3. Telegram Setup (Optional)

1. Message [@BotFather](https://t.me/BotFather) in Telegram
2. Create new bot: `/newbot`
3. Copy the **Bot Token**
4. Send a message to your bot
5. Get your **Chat ID**: `https://api.telegram.org/botTOKEN/getUpdates`
6. Enter both in plugin settings and click **Test Telegram**

## ⚙️ Configuration Guide

### Array Disk Monitoring

```bash
# Example configuration for typical Unraid setup
Array Disks: "sdb sdc sdd sde sdf sdg"     # Data disks only
Ignore Disks: "sda cache1 parity1 nvme0n1" # Cache, parity, system drives
```

### Network Monitoring

```bash
Network Interface: "eth0"           # Usually eth0 or br0
Network Threshold: "102400"         # 100 KB/s in bytes
Monitoring: "Enabled"               # Enable network activity checks
```

### Sleep Settings

```bash
Sleep Method: "Dynamix S3"          # Recommended (compatible with original)
WOL Options: "g"                    # MagicPacket support
Restart Samba: "Enabled"            # Ensures SMB shares work after wake
```

## 📊 Monitoring & Logging

### Real-time Monitoring
```bash
# Watch live log output
tail -f /tmp/smart-sleep.log

# Check current configuration
cat /boot/config/plugins/smart.sleep.manager/smart.sleep.manager.cfg

# Manual sleep check
/usr/local/emhttp/plugins/smart.sleep.manager/scripts/smart_sleep.sh
```

### Telegram Notifications

The plugin sends formatted notifications for:

- **⏳ Standby Timer Started**: When all conditions are met and countdown begins
- **🌙 Sleep Activated**: When server actually goes to sleep
- **💤 Sleep Blocked**: When active disks or network traffic prevent sleep (optional)

## 🛠️ Troubleshooting

### Sleep Not Working

1. **Check Hardware Support**
   ```bash
   # Verify S3 sleep support
   dmesg | grep -i "acpi.*sleep"
   cat /sys/power/state
   ```

2. **Verify Wake-on-LAN**
   ```bash
   # Check WOL settings
   ethtool eth0 | grep Wake-on
   # Should show: Wake-on: g
   ```

3. **Check Array Status**
   ```bash
   # All disks should show "standby" or "sleeping"
   hdparm -C /dev/sd[bcdefgh]
   ```

### Network Issues After Wake

- Enable **Force Gigabit** in advanced settings
- Enable **DHCP Renewal** if using DHCP
- Check **Samba Restart** is enabled

### Telegram Not Working

1. **Verify Bot Token Format**: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`
2. **Check Chat ID**: Should be numeric (can be negative for groups)
3. **Test Network**: Use the built-in test function
4. **Check Logs**: `/tmp/smart-sleep.log` shows Telegram attempts

## 📁 Project Structure

```
smart-sleep-manager/
├── 📄 smart.sleep.manager.plg      # Plugin installer
├── 📄 SmartSleepSettings.page      # Web UI configuration
├── 📄 SleepManager.php             # Manual sleep button
├── 📄 default.cfg                  # Default settings
├── 📁 include/
│   ├── update.sleep.php            # Configuration handler
│   ├── SleepMode.php               # Manual sleep execution
│   └── test_telegram.php           # Telegram test handler
└── 📁 scripts/
    ├── smart_sleep.sh              # Main sleep logic script
    ├── preRun                      # Pre-sleep hooks
    └── postRun                     # Post-wake hooks
```

## 🤝 Contributing

We welcome contributions! Please:

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/amazing-feature`
3. **Commit** your changes: `git commit -m 'Add amazing feature'`
4. **Push** to the branch: `git push origin feature/amazing-feature`
5. **Open** a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/skycryer/smart-sleep-manager.git
cd smart-sleep-manager

# Test on your Unraid server
scp smart.sleep.manager.plg root@unraid-server:/tmp/
ssh root@unraid-server "/usr/local/sbin/plugin install /tmp/smart.sleep.manager.plg"
```

## 📞 Support

- **📖 Documentation**: [Wiki](https://github.com/skycryer/smart-sleep-manager/wiki)
- **🐛 Bug Reports**: [GitHub Issues](https://github.com/skycryer/smart-sleep-manager/issues)
- **💬 Community**: [Unraid Forums](https://forums.unraid.net)
- **📧 Contact**: [GitHub Discussions](https://github.com/skycryer/smart-sleep-manager/discussions)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Bergware International** for the original Dynamix S3 Sleep plugin inspiration
- **Lime Technology** for the amazing Unraid platform
- **The Unraid Community** for continuous support and feedback
- **Original sleepy.sh contributors** for the foundational automation concepts

---

**Made with ❤️ for the Unraid Community**

*Save power, stay connected, sleep smart! 🌙*

## 🌟 Features

- **Automatisches Sleep-Management**: Überwacht Array-Disks und Netzwerk-Aktivität
- **Telegram-Benachrichtigungen**: Erhalte Updates über Sleep-Ereignisse
- **Web-UI Konfiguration**: Einfache Einrichtung über die Unraid-Weboberfläche
- **Dynamix S3 Sleep Kompatibilität**: Verwendet bewährte Sleep-Methoden
- **Intelligent Monitoring**: Unterscheidet zwischen Array- und Cache/Docker-Disks
- **Anpassbare Thresholds**: Konfiguriere Netzwerk- und Zeit-Limits
- **Wake-on-LAN Support**: Automatische WOL-Konfiguration vor Sleep

## 📋 Systemanforderungen

- Unraid 6.12.0 oder höher
- S3 Sleep-fähige Hardware
- Wake-on-LAN kompatible Netzwerkkarte
- Optional: Telegram-Bot für Benachrichtigungen

## 🚀 Installation

1. **Plugin installieren:**
   - Lade die `.plg` Datei in Unraid hoch (Apps > Install Plugin)
   - Oder füge die Plugin-URL zu Community Applications hinzu

2. **Grundkonfiguration:**
   - Gehe zu Settings > Smart Sleep Manager
   - Wähle Array-Disks zum Überwachen aus
   - Setze Ignore-Disks (Cache, Docker, Parity)
   - Konfiguriere Idle-Zeit (Standard: 15 Minuten)

3. **Optional - Telegram einrichten:**
   - Erstelle einen Bot mit @BotFather
   - Kopiere Bot-Token und Chat-ID
   - Teste die Konfiguration über die Web-UI

## ⚙️ Konfiguration

### Array-Disk Überwachung
- **Array Disks**: Wähle die zu überwachenden Array-Disks
- **Ignore Disks**: Cache-, Docker- und Parity-Disks ausschließen
- **Idle Time**: Zeit in Minuten ohne Disk-Aktivität vor Sleep

### Netzwerk-Überwachung
- **Network Interface**: Meist eth0 oder br0
- **Threshold**: Netzwerk-Traffic-Limit in Bytes/Sekunde
- **Monitoring**: Aktiviert/Deaktiviert Netzwerk-Checks

### Telegram-Benachrichtigungen
- **Bot Token**: Von @BotFather erhalten
- **Chat ID**: Eigene Telegram User-ID
- **Benachrichtigungstypen**: Timer-Start, Sleep, Blockierung

## 🔧 Erweiterte Einstellungen

- **Sleep Method**: Dynamix S3 (empfohlen) oder systemctl suspend
- **WOL Options**: Wake-on-LAN Konfiguration (meist 'g')
- **Samba Restart**: Automatischer Samba-Neustart nach Wake-up
- **Gigabit Force**: Netzwerk-Geschwindigkeit nach Wake erzwingen
- **DHCP Renewal**: DHCP-Lease nach Wake erneuern

## 📊 Monitoring

### Log-Datei
```bash
tail -f /tmp/smart-sleep.log
```

### Manueller Test
- Web-UI: "Run Sleep Check Now" Button
- Terminal: `/usr/local/emhttp/plugins/smart.sleep.manager/scripts/smart_sleep.sh`

### Cron-Job
Das Plugin führt alle 5 Minuten einen Check aus:
```
*/5 * * * * /usr/local/emhttp/plugins/smart.sleep.manager/scripts/smart_sleep.sh
```

## 🛠️ Troubleshooting

### Sleep funktioniert nicht
1. Prüfe Hardware S3-Support: `dmesg | grep -i "acpi.*sleep"`
2. Teste Wake-on-LAN: `ethtool eth0 | grep Wake-on`
3. Überprüfe Array-Status: Alle Disks müssen im Standby sein
4. Kontrolliere Log-Datei: `/tmp/smart-sleep.log`

### Netzwerk-Probleme nach Wake
1. Aktiviere "Force Gigabit"
2. Bei DHCP: Aktiviere "DHCP Renewal"
3. Überprüfe Samba-Restart

### Telegram funktioniert nicht
1. Prüfe Bot-Token Format: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`
2. Chat-ID finden: `https://api.telegram.org/botTOKEN/getUpdates`
3. Teste über Web-UI "Test Telegram" Button

## 📁 Dateistruktur

```
/usr/local/emhttp/plugins/smart.sleep.manager/
├── SmartSleepSettings.page    # Web-UI Konfiguration
├── SleepManager.php           # Manual Sleep Button
├── default.cfg                # Standard-Konfiguration
├── include/
│   ├── SleepMode.php         # Sleep-Ausführung
│   ├── update.sleep.php      # Konfigurations-Update
│   └── test_telegram.php     # Telegram-Test
└── scripts/
    ├── smart_sleep.sh        # Haupt-Sleep-Script
    ├── preRun                # Pre-Sleep Kommandos
    └── postRun               # Post-Wake Kommandos
```

## 🔄 Deinstallation

Das Plugin kann über Apps > Installed Plugins deinstalliert werden. Die Konfigurationsdateien bleiben in `/boot/config/plugins/smart.sleep.manager/` erhalten.

## 📞 Support

- **Log-Datei**: `/tmp/smart-sleep.log`
- **Konfiguration**: `/boot/config/plugins/smart.sleep.manager/smart.sleep.manager.cfg`
- **Unraid Forum**: [Plugin Support Thread]
- **GitHub Issues**: [Repository Issues]

## 📄 Lizenz

Dieses Plugin ist unter der MIT License lizenziert - siehe die [LICENSE](../LICENSE) Datei für Details.

Die MIT License erlaubt:
- ✅ Kommerzielle Nutzung
- ✅ Modifikation
- ✅ Weiterverteilung
- ✅ Private Nutzung
- ✅ Patent-Nutzung

## 🙏 Credits

- Basiert auf Dynamix S3 Sleep Plugin von Bergware International
- Inspiriert von der Unraid Community
- Telegram-Integration für moderne Benachrichtigungen