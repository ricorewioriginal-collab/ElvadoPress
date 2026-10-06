using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Brush = System.Windows.Media.Brush;
using Brushes = System.Windows.Media.Brushes;
using Button = System.Windows.Controls.Button;
using ComboBox = System.Windows.Controls.ComboBox;
using HorizontalAlignment = System.Windows.HorizontalAlignment;
using KeyEventArgs = System.Windows.Input.KeyEventArgs;
using Orientation = System.Windows.Controls.Orientation;
using TextBox = System.Windows.Controls.TextBox;

namespace ElvadoPress.App.Windows;

// KI-Assistent als eigene Seite im Inhaltsbereich (kein Overlay): Navigation und Player bleiben bedienbar.
// Spricht dieselbe CMS-API wie Portal und Android-App (assistant_chat / assistant_send).
public partial class MainWindow
{
    private static readonly HttpClient _aiHttp = new() { Timeout = TimeSpan.FromSeconds(60) };
    private readonly List<(string Role, string Text)> _aiHistory = new();
    private string _aiName = "Radio-Assistent";
    private string _aiGreeting = "Hi! Ich bin der Assistent. Frag mich, was gerade läuft, nach dem Sendeplan, unseren Sendern oder dem Podcast – oder schick dem Studio eine Nachricht.";
    private bool _aiMail = true;
    private bool _aiBusy;

    private async Task ShowAssistantAsync()
    {
        await Task.CompletedTask;
        Page("KI-ASSISTENT", _aiName);

        var head = Card();
        head.Child = Txt("KI-Antworten können Fehler enthalten. Der Assistent kennt Sender, Sendeplan, Podcast und News des Netzwerks.", 12, (Brush)FindResource("MutedBrush"));
        ContentHost.Children.Add(head);

        var messages = new StackPanel();
        var chips = new WrapPanel { Margin = new Thickness(0, 0, 0, 12) };

        void Chip(string label, Action action)
        {
            var b = new Button { Content = label, Margin = new Thickness(0, 0, 8, 8), Padding = new Thickness(14, 7, 14, 7) };
            b.Click += (_, _) => action();
            chips.Children.Add(b);
        }

        var input = new TextBox
        {
            Height = 44,
            VerticalContentAlignment = VerticalAlignment.Center,
            Background = (Brush)FindResource("Card2Brush"),
            Foreground = (Brush)FindResource("TextBrush"),
            BorderBrush = (Brush)FindResource("LineBrush"),
            Padding = new Thickness(14, 0, 14, 0),
            FontSize = 14
        };

        async Task AskAsync(string question)
        {
            if (_aiBusy || string.IsNullOrWhiteSpace(question)) return;
            _aiBusy = true;
            input.Text = "";
            AddBubble(messages, question, true);
            _aiHistory.Add(("user", question));
            var pending = AddBubble(messages, "…", false);
            string reply;
            try { reply = await ChatAsync(); }
            catch (HttpRequestException) { reply = "Ich erreiche gerade keine Verbindung. Sendeplan, News und Podcast findest du auch offline mit dem zuletzt geladenen Stand."; }
            catch (TaskCanceledException) { reply = "Die Antwort hat zu lange gedauert. Bitte versuch es gleich noch einmal."; }
            catch { reply = "Das hat leider nicht geklappt. Bitte versuch es gleich noch einmal."; }
            pending.Text = reply;
            _aiHistory.Add(("assistant", reply));
            if (_aiStations.Count > 0) AddStationsCard(messages, _aiStations);
            _aiBusy = false;
        }

        Chip("Was läuft gerade?", async () => await AskAsync("Was läuft gerade?"));
        Chip("Sendeplan heute", async () => await AskAsync("Wie sieht der Sendeplan heute aus?"));
        Chip("Nächste Sendungen", async () => await AskAsync("Welche Sendungen kommen als Nächstes?"));
        if (Brand.HasPodcast) Chip("Podcast", async () => await AskAsync("Was gibt es Neues im Podcast?"));
        Chip("News & Events", async () => await AskAsync("Welche News und Events gibt es aktuell?"));
        if (_aiMail) Chip("✉ Nachricht ans Studio", () => ShowStudioForm(messages));
        if (Brand.HasCommunity) Chip("🎙 Sprachnachricht", () => ShowWebPopup(Brand.Name + " · Sprachnachricht", "VOICEMAIL", new Uri(Brand.CommunityBase + "voicemsg.html")));
        ContentHost.Children.Add(chips);

        if (_aiHistory.Count == 0) AddBubble(messages, _aiGreeting, false);
        else foreach (var m in _aiHistory) AddBubble(messages, m.Text, m.Role == "user");
        ContentHost.Children.Add(messages);

        var row = new Grid { Margin = new Thickness(0, 12, 0, 0) };
        row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        row.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
        var send = new Button { Content = "Senden", Height = 44, Width = 96, Margin = new Thickness(10, 0, 0, 0), FontWeight = FontWeights.Bold };
        send.Click += async (_, _) => await AskAsync(input.Text.Trim());
        input.KeyDown += async (_, e) =>
        {
            if (e.Key == Key.Enter) { e.Handled = true; await AskAsync(input.Text.Trim()); }
        };
        Grid.SetColumn(send, 1);
        row.Children.Add(input);
        row.Children.Add(send);
        ContentHost.Children.Add(row);
        input.Focus();
    }

    private TextBlock AddBubble(StackPanel host, string text, bool user)
    {
        var tb = Txt(text, 14, user ? Brushes.Black : null);
        var bubble = new Border
        {
            Background = user ? (Brush)FindResource("PrimaryBrush") : (Brush)FindResource("CardBrush"),
            BorderBrush = (Brush)FindResource("LineBrush"),
            BorderThickness = new Thickness(user ? 0 : 1),
            CornerRadius = new CornerRadius(16),
            Padding = new Thickness(14, 10, 14, 10),
            Margin = new Thickness(user ? 80 : 0, 0, user ? 0 : 80, 10),
            HorizontalAlignment = user ? HorizontalAlignment.Right : HorizontalAlignment.Left,
            Child = tb
        };
        host.Children.Add(bubble);
        return tb;
    }

    private async Task<string> ChatAsync()
    {
        var last = _aiHistory.Skip(Math.Max(0, _aiHistory.Count - 8)).Select(m => new { role = m.Role, content = m.Text }).ToArray();
        var body = JsonSerializer.Serialize(new
        {
            messages = last,
            station = _currentStation?.Id ?? DefaultStationId,
            favorites = _favorites.ToArray()
        });
        _aiStations = new List<DirItem>();
        using var resp = await _aiHttp.PostAsync(Brand.SiteBase + "/cms/api.php?action=assistant_chat",
            new StringContent(body, Encoding.UTF8, "application/json"));
        var text = await resp.Content.ReadAsStringAsync();
        using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(text) ? "{}" : text);
        var root = doc.RootElement;
        // Karte "Aus dem Radioverzeichnis" (Marken mit Verzeichnis): Treffer, die per Knopf im Player starten
        if (root.TryGetProperty("cards", out var cards) && cards.ValueKind == JsonValueKind.Array)
            foreach (var c in cards.EnumerateArray())
                if (c.TryGetProperty("type", out var ct) && ct.GetString() == "stations" && c.TryGetProperty("items", out var its))
                    _aiStations.AddRange(RadioDirectory.ParseItems(its));
        var status = root.TryGetProperty("status", out var st) ? st.GetString() : "";
        if (status == "ok" && root.TryGetProperty("reply", out var reply) && !string.IsNullOrWhiteSpace(reply.GetString()))
            return reply.GetString()!.Trim();
        return root.TryGetProperty("message", out var msg) && !string.IsNullOrWhiteSpace(msg.GetString())
            ? msg.GetString()!
            : "Ich konnte gerade nicht antworten. Bitte versuch es gleich noch einmal.";
    }

    private void ShowStudioForm(StackPanel messages)
    {
        var card = Card();
        var form = new StackPanel();
        form.Children.Add(Txt("Nachricht ans Studio", 16, null, FontWeights.Bold));
        form.Children.Add(Txt("Wird direkt an das Studio der Website gesendet.", 12, (Brush)FindResource("MutedBrush")));

        var targets = new List<(string Id, string Label)> { ("netzwerk", "Alle Sender") };
        if (Brand.HasPodcast) targets.Add(("podcast", "Podcast"));
        foreach (var id in _owned) targets.Add((id, _stationCache.TryGetValue(id, out var known) && !string.IsNullOrWhiteSpace(known.Name) ? known.Name : id));
        var combo = new ComboBox { Margin = new Thickness(0, 10, 0, 8), Height = 38, DisplayMemberPath = "Label" };
        foreach (var t in targets) combo.Items.Add(new { t.Id, t.Label });
        var selected = Math.Max(0, targets.FindIndex(t => t.Id == (_currentStation?.Id ?? DefaultStationId)));
        combo.SelectedIndex = selected;
        form.Children.Add(combo);

        TextBox Field(string hint, bool multi = false)
        {
            var box = new TextBox
            {
                Margin = new Thickness(0, 0, 0, 8),
                Padding = new Thickness(10, 8, 10, 8),
                Background = (Brush)FindResource("Card2Brush"),
                Foreground = (Brush)FindResource("TextBrush"),
                BorderBrush = (Brush)FindResource("LineBrush"),
                ToolTip = hint,
                AcceptsReturn = multi,
                TextWrapping = TextWrapping.Wrap,
                MinHeight = multi ? 90 : 38
            };
            form.Children.Add(Txt(hint, 11, (Brush)FindResource("MutedBrush")));
            form.Children.Add(box);
            return box;
        }

        var name = Field("Dein Name");
        var mail = Field("E-Mail für Rückfragen (optional)");
        var msg = Field("Deine Nachricht, dein Gruß oder Musikwunsch", true);
        var status = Txt("", 12, (Brush)FindResource("MutedBrush"));
        var send = new Button { Content = "Senden", Height = 40, Width = 110, HorizontalAlignment = HorizontalAlignment.Left, Margin = new Thickness(0, 4, 0, 6) };
        send.Click += async (_, _) =>
        {
            if (string.IsNullOrWhiteSpace(name.Text)) { status.Text = "Bitte einen Namen angeben."; return; }
            if (msg.Text.Trim().Length < 3) { status.Text = "Bitte eine Nachricht eingeben."; return; }
            send.IsEnabled = false;
            status.Text = "Wird gesendet …";
            try
            {
                var target = targets[Math.Max(0, combo.SelectedIndex)];
                var body = JsonSerializer.Serialize(new { name = name.Text.Trim(), email = mail.Text.Trim(), station = target.Id, message = msg.Text.Trim(), hp = "" });
                using var resp = await _aiHttp.PostAsync(Brand.SiteBase + "/cms/api.php?action=assistant_send", new StringContent(body, Encoding.UTF8, "application/json"));
                using var doc = JsonDocument.Parse(await resp.Content.ReadAsStringAsync());
                var ok = doc.RootElement.TryGetProperty("status", out var st) && st.GetString() == "ok";
                if (ok)
                {
                    ContentHost.Children.Remove(card);
                    AddBubble(messages, $"Deine Nachricht an {target.Label} ist im Studio angekommen – danke dir!", false);
                    return;
                }
                status.Text = doc.RootElement.TryGetProperty("message", out var m) ? m.GetString() ?? "Senden fehlgeschlagen." : "Senden fehlgeschlagen.";
            }
            catch { status.Text = "Keine Verbindung – bitte später erneut versuchen."; }
            send.IsEnabled = true;
        };
        form.Children.Add(send);
        form.Children.Add(status);
        card.Child = form;
        ContentHost.Children.Insert(Math.Min(2, ContentHost.Children.Count), card);
    }
}
